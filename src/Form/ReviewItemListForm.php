<?php

namespace Drupal\reviews_by_url\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Link;

/**
 * Provides a form with filterable list of Review Item entities.
 */
class ReviewItemListForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The number of items per page.
   */
  const ITEMS_PER_PAGE = 50;

  /**
   * Constructs a ReviewItemListForm object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'review_item_list_filter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get current filter values from query parameters.
    $request = $this->getRequest();
    $search = $request->query->get('search', '');
    $url_filter = $request->query->get('url_filter', '');
    $status_filter = $request->query->get('status', '');

    // ===== Filter section =====
    $form['filters'] = [
      '#type' => 'details',
      '#title' => $this->t('Фильтры'),
      '#open' => !empty($search) || !empty($url_filter) || $status_filter !== '',
      '#attributes' => ['class' => ['container-inline']],
    ];

    $form['filters']['search'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Поиск'),
      '#placeholder' => $this->t('Имя автора или текст отзыва'),
      '#default_value' => $search,
      '#size' => 30,
    ];

    $form['filters']['url_filter'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URL страницы'),
      '#placeholder' => $this->t('/catalog/metallocherepitsa или <front>'),
      '#default_value' => $url_filter,
      '#size' => 30,
    ];

    $form['filters']['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Статус'),
      '#options' => [
        '' => $this->t('- Любой -'),
        '1' => $this->t('Опубликовано'),
        '0' => $this->t('Черновик'),
      ],
      '#default_value' => $status_filter,
    ];

    $form['filters']['apply'] = [
      '#type' => 'submit',
      '#value' => $this->t('Применить'),
    ];

    $form['filters']['reset'] = [
      '#type' => 'submit',
      '#value' => $this->t('Сбросить'),
      '#submit' => ['::resetForm'],
    ];

    // ===== Table =====
    $header = [
      'id' => $this->t('ID'),
      'author_name' => $this->t('Автор'),
      'rating' => $this->t('Рейтинг'),
      'city' => $this->t('Город'),
      'urls' => $this->t('URL страниц'),
      'status' => $this->t('Статус'),
      'weight' => $this->t('Вес'),
      'changed' => $this->t('Обновлено'),
    ];

    // Add operations column header.
    $header['operations'] = $this->t('Операции');

    $entities = $this->loadFilteredEntities($search, $url_filter, $status_filter);
    $rows = [];

    foreach ($entities as $entity) {
      $urls = $entity->getUrls();
      if (!empty($urls)) {
        $url_display = implode(', ', array_slice($urls, 0, 3));
        if (count($urls) > 3) {
          $url_display .= ' ... (+' . (count($urls) - 3) . ')';
        }
      }
      else {
        $url_display = $this->t('— Не привязан —');
      }

      $rating = $entity->getRating();
      $rating_display = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating) . ' ' . $rating . '/5';

      $row = [
        'id' => $entity->id(),
        'author_name' => $entity->getAuthorName(),
        'rating' => $rating_display,
        'city' => $entity->getCity() ?: '—',
        'urls' => $url_display,
        'status' => $entity->isPublished() ? $this->t('Опубликовано') : $this->t('Черновик'),
        'weight' => $entity->getWeight(),
        'changed' => \Drupal::service('date.formatter')->format(
          $entity->getChangedTimeAcrossTranslations(),
          'short'
        ),
      ];

      // Add operation links.
      $operations = [];
      $operations['edit'] = [
        'title' => $this->t('Редактировать'),
        'url' => Url::fromRoute('entity.review_item.edit_form', ['review_item' => $entity->id()]),
      ];
      $operations['delete'] = [
        'title' => $this->t('Удалить'),
        'url' => Url::fromRoute('entity.review_item.delete_form', ['review_item' => $entity->id()]),
      ];

      $row['operations'] = [
        'data' => [
          '#type' => 'operations',
          '#links' => $operations,
        ],
      ];

      $rows[] = $row;
    }

    $form['table'] = [
      '#type' => 'table',
      '#header' => ['id' => $this->t('ID'), 'author_name' => $this->t('Автор'), 'rating' => $this->t('Рейтинг'), 'city' => $this->t('Город'), 'urls' => $this->t('URL страниц'), 'status' => $this->t('Статус'), 'weight' => $this->t('Вес'), 'changed' => $this->t('Обновлено'), 'operations' => $this->t('Операции')],
      '#rows' => $rows,
      '#empty' => $this->t('Отзывы не найдены. Добавьте первый отзыв, нажав на кнопку выше.'),
      '#attributes' => ['class' => ['review-item-list-table']],
    ];

    // Pager.
    $form['pager'] = [
      '#type' => 'pager',
    ];

    return $form;
  }

  /**
   * Loads review item entities with applied filters.
   *
   * @param string $search
   *   Search string for author name or review text.
   * @param string $url_filter
   *   URL path to filter by.
   * @param string $status_filter
   *   Status filter ('1', '0', or '').
   *
   * @return \Drupal\reviews_by_url\Entity\ReviewItemInterface[]
   *   Array of matching review items.
   */
  protected function loadFilteredEntities($search, $url_filter, $status_filter) {
    $query = $this->entityTypeManager->getStorage('review_item')->getQuery();
    $query->accessCheck(TRUE);

    // Status filter.
    if ($status_filter !== '' && $status_filter !== NULL) {
      $query->condition('status', (int) $status_filter);
    }

    // Search by author name.
    if (!empty($search)) {
      $orGroup = $query->orConditionGroup()
        ->condition('author_name', $search, 'CONTAINS')
        ->condition('review_text', $search, 'CONTAINS');
      $query->condition($orGroup);
    }

    $query->sort('weight', 'ASC');
    $query->sort('id', 'ASC');
    $query->pager(self::ITEMS_PER_PAGE);

    $ids = $query->execute();

    if (empty($ids)) {
      return [];
    }

    $entities = $this->entityTypeManager->getStorage('review_item')->loadMultiple($ids);

    // URL filter requires post-query filtering because the urls field
    // is a multi-value string field and we need substring matching.
    if (!empty($url_filter)) {
      $url_filter_normalized = trim($url_filter);

      // Build a list of all URL variants that should match this filter.
      // For <front>, we also match the real front page path and its alias.
      $filter_variants = $this->getUrlFilterVariants($url_filter_normalized);

      $filtered = [];
      foreach ($entities as $entity) {
        $item_urls = $entity->getUrls();
        foreach ($item_urls as $item_url) {
          $item_url_trimmed = trim($item_url);

          // Normalize item URL (add leading slash unless it's <front>).
          $item_url_normalized = $item_url_trimmed;
          if ($item_url_trimmed !== '<front>' && !str_starts_with($item_url_trimmed, '/')) {
            $item_url_normalized = '/' . $item_url_trimmed;
          }

          // Check each filter variant against the item URL.
          foreach ($filter_variants as $variant) {
            // Exact match.
            if ($item_url_normalized === $variant || $item_url_trimmed === $variant) {
              $filtered[$entity->id()] = $entity;
              break 2;
            }
            // Partial/substring match.
            if (str_contains($item_url_normalized, $variant) || str_contains($item_url_trimmed, $variant)) {
              $filtered[$entity->id()] = $entity;
              break 2;
            }
            // Wildcard patterns in item URL (e.g. /catalog/*).
            if (str_ends_with($item_url_normalized, '/*')) {
              $base_path = rtrim(substr($item_url_normalized, 0, -2), '/');
              if ($variant === $base_path || str_starts_with($variant, $base_path . '/')) {
                $filtered[$entity->id()] = $entity;
                break 2;
              }
            }
          }
        }
      }
      return $filtered;
    }

    return $entities;
  }

  /**
   * Builds a list of URL filter variants from a user-supplied filter string.
   *
   * Handles special cases like <front> which maps to the real front page
   * path and its alias. For regular URLs, normalizes the leading slash.
   *
   * @param string $url_filter
   *   The raw URL filter value entered by the user.
   *
   * @return string[]
   *   Array of URL strings that should all match this filter.
   */
  protected function getUrlFilterVariants($url_filter) {
    $variants = [];

    // <front> is a special Drupal token for the home page.
    if ($url_filter === '<front>') {
      // Match the literal <front> token.
      $variants[] = '<front>';

      // Resolve <front> to the real system path (e.g. /node or /home).
      $front_path = \Drupal::config('system.site')->get('page.front');
      if (!empty($front_path)) {
        if (!str_starts_with($front_path, '/')) {
          $front_path = '/' . $front_path;
        }
        $variants[] = $front_path;

        // Also add the URL alias for the front page path.
        try {
          $alias = \Drupal::service('path_alias.manager')->getAliasByPath($front_path);
          if ($alias !== $front_path) {
            $variants[] = $alias;
          }
        } catch (\InvalidArgumentException $e) {
          // Skip.
        }
      }
      return $variants;
    }

    // Regular URL: normalize leading slash.
    $normalized = $url_filter;
    if (!str_starts_with($normalized, '/')) {
      $normalized = '/' . $normalized;
    }
    $variants[] = $normalized;

    // Also check if the filter is an alias and add the system path.
    try {
      $system_path = \Drupal::service('path_alias.manager')->getPathByAlias($normalized);
      if (!str_starts_with($system_path, '/')) {
        $system_path = '/' . $system_path;
      }
      if ($system_path !== $normalized) {
        $variants[] = $system_path;
      }
    } catch (\InvalidArgumentException $e) {
      // Skip.
    }

    return $variants;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $search = $form_state->getValue('search');
    $url_filter = $form_state->getValue('url_filter');
    $status = $form_state->getValue('status');

    $query = [];
    if (!empty($search)) {
      $query['search'] = $search;
    }
    if (!empty($url_filter)) {
      $query['url_filter'] = $url_filter;
    }
    if ($status !== '' && $status !== NULL) {
      $query['status'] = $status;
    }

    $form_state->setRedirect('entity.review_item.collection', [], ['query' => $query]);
  }

  /**
   * Reset form callback.
   */
  public function resetForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('entity.review_item.collection');
  }

}
