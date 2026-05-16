<?php

namespace Drupal\reviews_by_url\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller for AJAX pagination in the "All Reviews" block.
 */
class ReviewAjaxController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Number of reviews per page.
   */
  const REVIEWS_PER_PAGE = 12;

  /**
   * Constructs a ReviewAjaxController object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.factory')
    );
  }

  /**
   * Returns a page of reviews as JSON for AJAX pagination.
   *
   * @param int $page
   *   The page number (1-based).
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with reviews HTML and pager data.
   */
  public function getPage($page = 1) {
    $config = $this->configFactory->get('reviews_by_url.settings');

    if ($page < 1) {
      $page = 1;
    }

    // Count total published reviews.
    $count_query = $this->entityTypeManager->getStorage('review_item')->getQuery();
    $count_query->condition('status', 1)->accessCheck(TRUE);
    $total = $count_query->count()->execute();

    $total_pages = $total > 0 ? (int) ceil($total / self::REVIEWS_PER_PAGE) : 1;

    if ($page > $total_pages) {
      $page = $total_pages;
    }

    // Load reviews for requested page.
    $offset = ($page - 1) * self::REVIEWS_PER_PAGE;
    $query = $this->entityTypeManager->getStorage('review_item')->getQuery();
    $query->condition('status', 1)
      ->sort('weight', 'ASC')
      ->sort('id', 'ASC')
      ->range($offset, self::REVIEWS_PER_PAGE)
      ->accessCheck(TRUE);

    $ids = $query->execute();
    $review_items = $this->entityTypeManager->getStorage('review_item')->loadMultiple($ids);

    $items = [];
    foreach ($review_items as $review_item) {
      $review_render = [
        '#type' => 'processed_text',
        '#text' => $review_item->getReviewText(),
        '#format' => $review_item->getReviewTextFormat() ?: 'basic_html',
        '#langcode' => $review_item->language()->getId(),
      ];

      $author_name = $review_item->getAuthorName();
      $initials = '';
      $name_parts = preg_split('/\s+/', trim($author_name));
      foreach ($name_parts as $part) {
        if (!empty($part)) {
          $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }
        if (mb_strlen($initials) >= 2) {
          break;
        }
      }
      if (empty($initials)) {
        $initials = '?';
      }

      $review_date = '';
      if ($config->get('show_date')) {
        $date_field = $review_item->getReviewDate();
        if (!empty($date_field)) {
          $review_date = $date_field->format('d.m.Y');
        }
        else {
          $review_date = \Drupal::service('date.formatter')->format(
            $review_item->getCreatedTime(),
            'custom',
            'd.m.Y'
          );
        }
      }

      // Build the card HTML.
      $card_html = [
        '#theme' => 'reviews_by_url_card',
        '#item' => [
          'id' => $review_item->id(),
          'author_name' => $author_name,
          'author_initials' => $initials,
          'review_text' => \Drupal::service('renderer')->renderPlain($review_render),
          'rating' => $review_item->getRating(),
          'city' => $config->get('show_city') ? $review_item->getCity() : '',
          'date' => $review_date,
        ],
        '#show_rating' => $config->get('show_rating') ? TRUE : FALSE,
        '#show_date' => $config->get('show_date') ? TRUE : FALSE,
        '#show_city' => $config->get('show_city') ? TRUE : FALSE,
      ];

      $items[] = \Drupal::service('renderer')->renderPlain($card_html);
    }

    // Build pager data.
    $pager = $this->buildPager($page, $total_pages);

    return new JsonResponse([
      'page' => $page,
      'total_pages' => $total_pages,
      'items_html' => $items,
      'pager' => $pager,
    ]);
  }

  /**
   * Builds pager data for AJAX pagination.
   *
   * @param int $current_page
   *   Current page number (1-based).
   * @param int $total_pages
   *   Total number of pages.
   *
   * @return array
   *   Pager data array.
   */
  protected function buildPager($current_page, $total_pages) {
    if ($total_pages <= 1) {
      return [
        'current' => 1,
        'total' => 1,
        'pages' => [],
      ];
    }

    $pages = [];

    $pages[] = [
      'number' => 1,
      'is_current' => $current_page === 1,
      'is_ellipsis' => FALSE,
    ];

    $range = 2;
    $start = max(2, $current_page - $range);
    $end = min($total_pages - 1, $current_page + $range);

    if ($start > 2) {
      $pages[] = [
        'number' => 0,
        'is_current' => FALSE,
        'is_ellipsis' => TRUE,
      ];
    }

    for ($i = $start; $i <= $end; $i++) {
      $pages[] = [
        'number' => $i,
        'is_current' => $current_page === $i,
        'is_ellipsis' => FALSE,
      ];
    }

    if ($end < $total_pages - 1) {
      $pages[] = [
        'number' => 0,
        'is_current' => FALSE,
        'is_ellipsis' => TRUE,
      ];
    }

    if ($total_pages > 1) {
      $pages[] = [
        'number' => $total_pages,
        'is_current' => $current_page === $total_pages,
        'is_ellipsis' => FALSE,
      ];
    }

    return [
      'current' => $current_page,
      'total' => $total_pages,
      'pages' => $pages,
    ];
  }

}
