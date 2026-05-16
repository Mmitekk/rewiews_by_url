<?php

namespace Drupal\reviews_by_url\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a 'Reviews by URL' block.
 *
 * @Block(
 *   id = "reviews_by_url_block",
 *   admin_label = @Translation("Reviews by URL — Отзывы"),
 *   category = @Translation("Custom")
 * )
 */
class ReviewBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current path stack.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $currentPath;

  /**
   * The path alias manager.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The path matcher service.
   *
   * @var \Drupal\Core\Path\PathMatcherInterface
   */
  protected $pathMatcher;

  /**
   * Number of reviews per page for "show all" mode.
   */
  const REVIEWS_PER_PAGE = 12;

  /**
   * Constructs a ReviewBlock object.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    CurrentPathStack $current_path,
    AliasManagerInterface $alias_manager,
    RequestStack $request_stack,
    PathMatcherInterface $path_matcher
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->currentPath = $current_path;
    $this->aliasManager = $alias_manager;
    $this->requestStack = $request_stack;
    $this->pathMatcher = $path_matcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('path.current'),
      $container->get('path_alias.manager'),
      $container->get('request_stack'),
      $container->get('path.matcher')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'label_display' => FALSE,
      'show_all' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $form['show_all'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Показать все отзывы (режим «Все отзывы»)'),
      '#description' => $this->t('Если включено, блок будет выводить ВСЕ опубликованные отзывы с пагинацией (по 12 штук на страницу), без фильтрации по текущему URL. Пагинация работает через AJAX — URL страницы не изменяется. Разместите этот блок на странице «Все отзывы».'),
      '#default_value' => $this->configuration['show_all'] ?? FALSE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, \Drupal\Core\Form\FormStateInterface $form_state) {
    $this->configuration['show_all'] = (bool) $form_state->getValue('show_all');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = \Drupal::config('reviews_by_url.settings');

    // If "show_all" mode is enabled, load ALL reviews with AJAX pagination.
    if (!empty($this->configuration['show_all'])) {
      return $this->buildAllReviews($config);
    }

    // Default behavior: filter reviews by current page URL.
    $current_urls = $this->getCurrentUrls();

    if (empty($current_urls)) {
      return $this->buildEmpty($config);
    }

    $review_items = $this->loadReviewItemsForUrls($current_urls);

    if (empty($review_items)) {
      return $this->buildEmpty($config);
    }

    $items = $this->buildReviewItems($review_items, $config);

    // Get URL for "All Reviews" button.
    $all_reviews_url = $config->get('all_reviews_url') ?: '';

    // Hide the "All Reviews" link when we are already on that page
    // (including pagination paths like /vse-otzyvy/page/2).
    if (!empty($all_reviews_url) && $this->isOnAllReviewsPage($all_reviews_url)) {
      $all_reviews_url = '';
    }

    $build = [
      '#theme' => 'reviews_by_url_block',
      '#title' => $config->get('block_title') ?: '',
      '#items' => $items,
      '#show_rating' => $config->get('show_rating') ? TRUE : FALSE,
      '#show_date' => $config->get('show_date') ? TRUE : FALSE,
      '#show_city' => $config->get('show_city') ? TRUE : FALSE,
      '#all_reviews_url' => $all_reviews_url,
      '#empty_message' => '',
      '#pager' => [],
      '#css_variables' => $this->buildCssVariables($config),
      '#attached' => [
        'library' => [
          'reviews_by_url/review_block',
        ],
      ],
      '#cache' => [
        'contexts' => ['url.path', 'url.query_args'],
        'tags' => ['review_item_list'],
        'max-age' => 3600,
      ],
    ];

    return $build;
  }

  /**
   * Builds the "All Reviews" variant with AJAX pagination.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The module configuration.
   *
   * @return array
   *   A render array.
   */
  protected function buildAllReviews($config) {
    // Count total published reviews.
    $count_query = $this->entityTypeManager->getStorage('review_item')->getQuery();
    $count_query->condition('status', 1)->accessCheck(TRUE);
    $total = $count_query->count()->execute();

    if ($total === 0) {
      return $this->buildEmpty($config);
    }

    $total_pages = (int) ceil($total / self::REVIEWS_PER_PAGE);

    // Load ALL published reviews (first page for initial load).
    // AJAX pagination will request subsequent pages.
    $query = $this->entityTypeManager->getStorage('review_item')->getQuery();
    $query->condition('status', 1)
      ->sort('review_date', 'DESC')
      ->sort('created', 'DESC')
      ->sort('id', 'DESC')
      ->range(0, self::REVIEWS_PER_PAGE)
      ->accessCheck(TRUE);

    $ids = $query->execute();
    $review_items = $this->entityTypeManager->getStorage('review_item')->loadMultiple($ids);

    $items = $this->buildReviewItems($review_items, $config);

    // Build pager data.
    $pager = $this->buildPager(1, $total_pages);

    $build = [
      '#theme' => 'reviews_by_url_block',
      '#title' => $config->get('block_title') ?: '',
      '#items' => $items,
      '#show_rating' => $config->get('show_rating') ? TRUE : FALSE,
      '#show_date' => $config->get('show_date') ? TRUE : FALSE,
      '#show_city' => $config->get('show_city') ? TRUE : FALSE,
      '#all_reviews_url' => '',
      '#empty_message' => '',
      '#pager' => $pager,
      '#show_all_mode' => TRUE,
      '#css_variables' => $this->buildCssVariables($config),
      '#attached' => [
        'library' => [
          'reviews_by_url/review_block',
        ],
        'drupalSettings' => [
          'reviewsByUrl' => [
            'showAllMode' => TRUE,
            'perPage' => self::REVIEWS_PER_PAGE,
            'totalPages' => $total_pages,
            'ajaxUrl' => '/reviews-by-url/ajax/page',
          ],
        ],
      ],
      '#cache' => [
        'contexts' => ['url.path'],
        'tags' => ['review_item_list'],
        'max-age' => 3600,
      ],
    ];

    return $build;
  }

  /**
   * Builds review items array from loaded entities.
   *
   * @param \Drupal\reviews_by_url\Entity\ReviewItemInterface[] $review_items
   *   Array of review item entities.
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The module configuration.
   *
   * @return array
   *   Array of review item data for the template.
   */
  protected function buildReviewItems(array $review_items, $config) {
    $items = [];
    foreach ($review_items as $review_item) {
      $review_render = [
        '#type' => 'processed_text',
        '#text' => $review_item->getReviewText(),
        '#format' => $review_item->getReviewTextFormat() ?: 'basic_html',
        '#langcode' => $review_item->language()->getId(),
      ];

      // Format review date.
      $review_date = '';
      if ($config->get('show_date')) {
        $date_field = $review_item->getReviewDate();
        if (!empty($date_field)) {
          $review_date = $date_field->format('d.m.Y');
        }
        else {
          // Fallback to created date.
          $review_date = \Drupal::service('date.formatter')->format(
            $review_item->getCreatedTime(),
            'custom',
            'd.m.Y'
          );
        }
      }

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
    return $items;
  }

  /**
   * Checks whether the current page is the "All Reviews" page.
   *
   * Compares the current request path against the configured all_reviews_url
   * and its pagination variants (e.g. /vse-otzyvy, /vse-otzyvy/page/2).
   *
   * @param string $all_reviews_url
   *   The configured URL for the "All Reviews" page (e.g. /vse-otzyvy).
   *
   * @return bool
   *   TRUE if the current page is the "All Reviews" page.
   */
  protected function isOnAllReviewsPage($all_reviews_url) {
    $request = $this->requestStack->getCurrentRequest();
    if (!$request) {
      return FALSE;
    }

    // Normalize the configured URL: ensure leading slash, strip trailing.
    $normalized_url = trim($all_reviews_url);
    if (!empty($normalized_url) && !str_starts_with($normalized_url, '/')) {
      $normalized_url = '/' . $normalized_url;
    }
    $normalized_url = rtrim($normalized_url, '/');

    if (empty($normalized_url)) {
      return FALSE;
    }

    // Get current request path (what user sees in browser).
    $current_path = $request->getPathInfo();
    $current_path = rtrim($current_path, '/');

    // Exact match: /vse-otzyvy.
    if ($current_path === $normalized_url) {
      return TRUE;
    }

    // Pagination match: /vse-otzyvy/page/2, /vse-otzyvy/page/12, etc.
    if (preg_match('#^' . preg_quote($normalized_url, '#') . '/page/\d+$#', $current_path)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Builds the empty state render array.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The module configuration.
   *
   * @return array
   *   A render array.
   */
  protected function buildEmpty($config) {
    $empty_message = $config->get('empty_message');
    if (empty($empty_message)) {
      return [
        '#cache' => [
          'contexts' => ['url.path'],
          'tags' => ['review_item_list'],
          'max-age' => 3600,
        ],
      ];
    }

    return [
      '#theme' => 'reviews_by_url_block',
      '#title' => $config->get('block_title') ?: '',
      '#items' => [],
      '#show_rating' => FALSE,
      '#show_date' => FALSE,
      '#show_city' => FALSE,
      '#empty_message' => $empty_message,
      '#pager' => [],
      '#show_all_mode' => FALSE,
      '#css_variables' => $this->buildCssVariables($config),
      '#attached' => [
        'library' => [
          'reviews_by_url/review_block',
        ],
      ],
      '#cache' => [
        'contexts' => ['url.path'],
        'tags' => ['review_item_list'],
        'max-age' => 3600,
      ],
    ];
  }

  /**
   * Builds pager data for AJAX-based pagination.
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

    // Always show first page.
    $pages[] = [
      'number' => 1,
      'url' => '',
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
        'url' => '',
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
        'url' => '',
        'is_current' => $current_page === $total_pages,
        'is_ellipsis' => FALSE,
      ];
    }

    return [
      'current' => $current_page,
      'total' => $total_pages,
      'pages' => $pages,
      'prev_url' => '',
      'next_url' => '',
      'first_url' => '',
      'last_url' => '',
    ];
  }

  /**
   * Gets all URL variants for the current page.
   *
   * @return string[]
   *   Array of URL paths to match against.
   */
  protected function getCurrentUrls() {
    $urls = [];

    // 1. Get current path from the path stack (system path).
    $system_path = $this->currentPath->getPath();
    if (!str_starts_with($system_path, '/')) {
      $system_path = '/' . $system_path;
    }
    $urls[] = $system_path;

    // 2. Get the alias for the system path.
    try {
      $alias = $this->aliasManager->getAliasByPath($system_path);
      if ($alias !== $system_path) {
        $urls[] = $alias;
      }
    } catch (\InvalidArgumentException $e) {
      // Path not found in alias storage, skip.
    }

    // 3. Get the request URI (what user sees in browser).
    $request = $this->requestStack->getCurrentRequest();
    if ($request) {
      $request_uri = $request->getRequestUri();
      // Strip query string.
      $request_path = '/' . ltrim(strtok($request_uri, '?'), '/');
      if (!in_array($request_path, $urls)) {
        $urls[] = $request_path;
      }

      // Also get system path from the request URI alias.
      try {
        $system_from_alias = $this->aliasManager->getPathByAlias($request_path);
        if (!str_starts_with($system_from_alias, '/')) {
          $system_from_alias = '/' . $system_from_alias;
        }
        if (!in_array($system_from_alias, $urls)) {
          $urls[] = $system_from_alias;
        }
      } catch (\InvalidArgumentException $e) {
        // Alias not found, skip.
      }
    }

    // 4. If this is the front page, add the <front> token and the
    //    configured front page path from Drupal settings.
    if ($this->pathMatcher->isFrontPage()) {
      $urls[] = '<front>';

      $front_path = \Drupal::config('system.site')->get('page.front');
      if (!empty($front_path)) {
        if (!str_starts_with($front_path, '/')) {
          $front_path = '/' . $front_path;
        }
        if (!in_array($front_path, $urls)) {
          $urls[] = $front_path;
        }

        // And the alias for the front page system path.
        try {
          $front_alias = $this->aliasManager->getAliasByPath($front_path);
          if ($front_alias !== $front_path && !in_array($front_alias, $urls)) {
            $urls[] = $front_alias;
          }
        } catch (\InvalidArgumentException $e) {
          // Skip.
        }
      }
    }

    // Remove trailing slashes for consistency (except root).
    $urls = array_map(function ($url) {
      if ($url !== '/' && $url !== '<front>' && str_ends_with($url, '/')) {
        return rtrim($url, '/');
      }
      return $url;
    }, $urls);

    return array_unique(array_filter($urls));
  }

  /**
   * Resolves the <front> token to the actual front page path.
   *
   * @return string
   *   The front page path with leading slash.
   */
  protected function resolveFrontPath() {
    $front_path = \Drupal::config('system.site')->get('page.front');
    if (!empty($front_path)) {
      if (!str_starts_with($front_path, '/')) {
        $front_path = '/' . $front_path;
      }
      return $front_path;
    }
    return '/node';
  }

  /**
   * Loads review items that are assigned to any of the given URLs.
   *
   * @param string[] $urls
   *   Array of URL paths to match (including <front> if front page).
   *
   * @return \Drupal\reviews_by_url\Entity\ReviewItemInterface[]
   *   Array of matching review items, sorted by weight and ID.
   */
  protected function loadReviewItemsForUrls(array $urls) {
    if (empty($urls)) {
      return [];
    }

    // First, load all published review items.
    $query = $this->entityTypeManager->getStorage('review_item')->getQuery();
    $query->condition('status', 1)
      ->sort('review_date', 'DESC')
      ->sort('created', 'DESC')
      ->sort('id', 'DESC')
      ->accessCheck(TRUE);

    $ids = $query->execute();

    if (empty($ids)) {
      return [];
    }

    $review_items = $this->entityTypeManager->getStorage('review_item')->loadMultiple($ids);
    $matching_items = [];

    // Resolve <front> to real paths for alias lookups.
    $front_path = $this->resolveFrontPath();

    foreach ($review_items as $review_item) {
      $item_urls = $review_item->getUrls();
      if (empty($item_urls)) {
        continue;
      }

      // Normalize item URLs for comparison.
      $normalized_item_urls = array_map(function ($url) {
        $url = trim($url);
        // Don't touch <front> — it's a special token.
        if ($url === '<front>') {
          return $url;
        }
        if (!empty($url) && !str_starts_with($url, '/')) {
          $url = '/' . $url;
        }
        if ($url !== '/' && str_ends_with($url, '/')) {
          $url = rtrim($url, '/');
        }
        return $url;
      }, $item_urls);

      // Check for intersection.
      foreach ($normalized_item_urls as $item_url) {

        // --- Special token: <front> ---
        if ($item_url === '<front>') {
          if (in_array('<front>', $urls) || in_array($front_path, $urls)) {
            $matching_items[$review_item->id()] = $review_item;
            break;
          }
          // Also check alias of the front page path.
          try {
            $front_alias = $this->aliasManager->getAliasByPath($front_path);
            if (in_array($front_alias, $urls)) {
              $matching_items[$review_item->id()] = $review_item;
              break;
            }
          } catch (\InvalidArgumentException $e) {
            // Skip.
          }
          continue;
        }

        // Direct match.
        if (in_array($item_url, $urls)) {
          $matching_items[$review_item->id()] = $review_item;
          break;
        }

        // Also check if the item URL is an alias and we have the system path.
        try {
          $system_path_from_alias = $this->aliasManager->getPathByAlias($item_url);
          if (!str_starts_with($system_path_from_alias, '/')) {
            $system_path_from_alias = '/' . $system_path_from_alias;
          }
          if (in_array($system_path_from_alias, $urls)) {
            $matching_items[$review_item->id()] = $review_item;
            break;
          }
        } catch (\InvalidArgumentException $e) {
          // Not a valid alias, skip.
        }

        // Also check if the item URL is a system path and we have the alias.
        try {
          $alias_from_system = $this->aliasManager->getAliasByPath($item_url);
          if ($alias_from_system !== $item_url && in_array($alias_from_system, $urls)) {
            $matching_items[$review_item->id()] = $review_item;
            break;
          }
        } catch (\InvalidArgumentException $e) {
          // Not a valid system path, skip.
        }

        // Wildcard matching: if item URL ends with /*, match any subpath.
        if (str_ends_with($item_url, '/*')) {
          $base_path = rtrim(substr($item_url, 0, -2), '/');
          foreach ($urls as $current_url) {
            if ($current_url === $base_path || str_starts_with($current_url, $base_path . '/')) {
              $matching_items[$review_item->id()] = $review_item;
              break 2;
            }
          }
        }
      }
    }

    return $matching_items;
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
      '--review-color-card-border' => $config->get('color_card_border') ?: '#157fed',
      '--review-color-section-bg' => $config->get('color_section_bg') ?: '#f2f2f2',
    ];

    $parts = [];
    foreach ($vars as $name => $value) {
      $parts[] = $name . ': ' . $value;
    }

    return implode('; ', $parts);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return ['url.path', 'url.query_args'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return ['review_item_list'];
  }

}
