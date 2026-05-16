<?php

namespace Drupal\reviews_by_url\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for the "All Reviews" page with path-based pagination.
 */
class ReviewAllController extends ControllerBase {

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
   * Constructs a ReviewAllController object.
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
   * Builds the "All Reviews" page.
   *
   * @param int $page
   *   The page number (1-based). Defaults to 1.
   *
   * @return array
   *   A render array.
   */
  public function listReviews($page = 1) {
    $config = $this->configFactory->get('reviews_by_url.settings');

    if ($page < 1) {
      $page = 1;
    }

    // Count total published reviews.
    $count_query = $this->entityTypeManager->getStorage('review_item')->getQuery();
    $count_query->condition('status', 1)->accessCheck(TRUE);
    $total = $count_query->count()->execute();

    if ($total === 0) {
      return [
        '#theme' => 'reviews_by_url_all_page',
        '#title' => $config->get('block_title') ?: $this->t('Отзывы'),
        '#items' => [],
        '#show_rating' => $config->get('show_rating') ? TRUE : FALSE,
        '#show_date' => $config->get('show_date') ? TRUE : FALSE,
        '#show_city' => $config->get('show_city') ? TRUE : FALSE,
        '#pager' => [],
        '#empty_message' => $config->get('empty_message') ?: $this->t('Отзывов пока нет.'),
        '#css_variables' => $this->buildCssVariables($config),
        '#attached' => [
          'library' => [
            'reviews_by_url/review_block',
          ],
        ],
        '#cache' => [
          'tags' => ['review_item_list'],
          'contexts' => ['url'],
          'max-age' => 3600,
        ],
      ];
    }

    $total_pages = (int) ceil($total / self::REVIEWS_PER_PAGE);

    // If page exceeds total, show last page.
    if ($page > $total_pages) {
      $page = $total_pages;
    }

    // Load reviews for current page.
    $offset = ($page - 1) * self::REVIEWS_PER_PAGE;
    $query = $this->entityTypeManager->getStorage('review_item')->getQuery();
    $query->condition('status', 1)
      ->sort('review_date', 'DESC')
      ->sort('created', 'DESC')
      ->sort('id', 'DESC')
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

      // Generate author initials for avatar.
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

      // Format review date.
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

      $items[] = [
        'id' => $review_item->id(),
        'author_name' => $author_name,
        'author_initials' => $initials,
        'review_text' => \Drupal::service('renderer')->renderPlain($review_render),
        'rating' => $review_item->getRating(),
        'city' => $config->get('show_city') ? $review_item->getCity() : '',
        'date' => $review_date,
      ];
    }

    // Build pager data.
    $pager = $this->buildPager($page, $total_pages, $config->get('all_reviews_url') ?: '/vse-otzyvy');

    return [
      '#theme' => 'reviews_by_url_all_page',
      '#title' => $config->get('block_title') ?: $this->t('Отзывы'),
      '#items' => $items,
      '#show_rating' => $config->get('show_rating') ? TRUE : FALSE,
      '#show_date' => $config->get('show_date') ? TRUE : FALSE,
      '#show_city' => $config->get('show_city') ? TRUE : FALSE,
      '#pager' => $pager,
      '#empty_message' => '',
      '#css_variables' => $this->buildCssVariables($config),
      '#attached' => [
        'library' => [
          'reviews_by_url/review_block',
        ],
      ],
      '#cache' => [
        'tags' => ['review_item_list'],
        'contexts' => ['url'],
        'max-age' => 3600,
      ],
    ];
  }

  /**
   * Builds pager data for the template.
   *
   * Generates clean path-based URLs like:
   * - /vse-otzyvy (first page)
   * - /vse-otzyvy/page/2
   * - /vse-otzyvy/page/3
   *
   * @param int $current_page
   *   Current page number (1-based).
   * @param int $total_pages
   *   Total number of pages.
   * @param string $base_path
   *   Base path for the reviews page.
   *
   * @return array
   *   Pager data array with keys: current, total, pages.
   */
  protected function buildPager($current_page, $total_pages, $base_path) {
    if ($total_pages <= 1) {
      return [
        'current' => 1,
        'total' => 1,
        'pages' => [],
      ];
    }

    // Normalize base path.
    $base_path = trim($base_path, '/');

    $pages = [];

    // Helper to generate URL for a page.
    $getUrl = function ($page_num) use ($base_path) {
      if ($page_num <= 1) {
        return '/' . $base_path;
      }
      return '/' . $base_path . '/page/' . $page_num;
    };

    // Always show first page.
    $pages[] = [
      'number' => 1,
      'url' => $getUrl(1),
      'is_current' => $current_page === 1,
      'is_ellipsis' => FALSE,
    ];

    // Calculate range of pages to show around current.
    $range = 2;
    $start = max(2, $current_page - $range);
    $end = min($total_pages - 1, $current_page + $range);

    // Ellipsis after first page.
    if ($start > 2) {
      $pages[] = [
        'number' => 0,
        'url' => '',
        'is_current' => FALSE,
        'is_ellipsis' => TRUE,
      ];
    }

    // Middle pages.
    for ($i = $start; $i <= $end; $i++) {
      $pages[] = [
        'number' => $i,
        'url' => $getUrl($i),
        'is_current' => $current_page === $i,
        'is_ellipsis' => FALSE,
      ];
    }

    // Ellipsis before last page.
    if ($end < $total_pages - 1) {
      $pages[] = [
        'number' => 0,
        'url' => '',
        'is_current' => FALSE,
        'is_ellipsis' => TRUE,
      ];
    }

    // Always show last page (if more than 1 page).
    if ($total_pages > 1) {
      $pages[] = [
        'number' => $total_pages,
        'url' => $getUrl($total_pages),
        'is_current' => $current_page === $total_pages,
        'is_ellipsis' => FALSE,
      ];
    }

    return [
      'current' => $current_page,
      'total' => $total_pages,
      'pages' => $pages,
      'prev_url' => $current_page > 1 ? $getUrl($current_page - 1) : '',
      'next_url' => $current_page < $total_pages ? $getUrl($current_page + 1) : '',
      'first_url' => $getUrl(1),
      'last_url' => $getUrl($total_pages),
    ];
  }

  /**
   * Builds CSS custom properties string from module configuration.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The module configuration.
   *
   * @return string
   *   Inline style string for CSS custom properties.
   */
  protected function buildCssVariables($config) {
    $vars = [
      '--review-color-accent' => $config->get('color_accent') ?: '#157fed',
      '--review-color-author-text' => $config->get('color_author_text') ?: '#333333',
      '--review-color-review-text' => $config->get('color_review_text') ?: '#333333',
      '--review-color-border' => $config->get('color_border') ?: '#eeeeee',
      '--review-color-date-text' => $config->get('color_date_text') ?: '#999999',
      '--review-color-star' => $config->get('color_star') ?: '#d89c11',
      '--review-color-card-bg' => $config->get('color_card_bg') ?: '#ffffff',
      '--review-color-card-border' => $config->get('color_card_border') ?: '#eeeeee',
      '--review-color-section-bg' => $config->get('color_section_bg') ?: '#f2f2f2',
    ];

    $parts = [];
    foreach ($vars as $name => $value) {
      $parts[] = $name . ': ' . $value;
    }

    return implode('; ', $parts);
  }

  /**
   * Page title callback.
   */
  public function pageTitle() {
    $config = $this->configFactory->get('reviews_by_url.settings');
    return $config->get('block_title') ?: $this->t('Отзывы');
  }

}
