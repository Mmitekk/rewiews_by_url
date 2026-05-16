/**
 * @file
 * JavaScript behaviors for the Reviews by URL block.
 *
 * Provides:
 * - Schema.org Review structured data for SEO.
 * - AJAX pagination for "All Reviews" mode (no URL deformation).
 */

(function (Drupal, drupalSettings) {
  'use strict';

  // ===========================================================================
  // Schema.org Review JSON-LD
  // ===========================================================================

  /**
   * Adds Schema.org Review structured data as JSON-LD.
   *
   * @param {HTMLElement} context
   *   The DOM context.
   */
  function addSchemaMarkup(context) {
    var wrapper = context.querySelector('.reviews-by-url-wrapper');
    if (!wrapper) {
      return;
    }

    var cards = wrapper.querySelectorAll('.reviews-by-url-card');
    if (cards.length === 0) {
      return;
    }

    // Check if we already added schema markup.
    if (document.getElementById('reviews-by-url-schema')) {
      return;
    }

    // Calculate aggregate rating.
    var totalRating = 0;
    var reviewCount = 0;

    var reviews = [];

    cards.forEach(function (card) {
      var authorEl = card.querySelector('.reviews-by-url-card__author');
      var textEl = card.querySelector('.reviews-by-url-card__text');
      var ratingEl = card.querySelector('.reviews-by-url-card__rating');
      var dateEl = card.querySelector('.reviews-by-url-card__date');

      if (!authorEl || !textEl) {
        return;
      }

      var ratingValue = 5;
      if (ratingEl) {
        var ratingValueEl = ratingEl.querySelector('.reviews-by-url-card__rating-value');
        if (ratingValueEl) {
          ratingValue = parseInt(ratingValueEl.textContent.trim(), 10) || 5;
        }
      }

      totalRating += ratingValue;
      reviewCount++;

      var review = {
        '@type': 'Review',
        'author': {
          '@type': 'Person',
          'name': authorEl.textContent.trim()
        },
        'reviewBody': textEl.textContent.trim(),
        'reviewRating': {
          '@type': 'Rating',
          'ratingValue': ratingValue,
          'bestRating': 5
        }
      };

      if (dateEl) {
        var dateText = dateEl.textContent.trim();
        var dateMatch = dateText.match(/(\d{2})\.(\d{2})\.(\d{4})/);
        if (dateMatch) {
          review.datePublished = dateMatch[3] + '-' + dateMatch[2] + '-' + dateMatch[1];
        }
      }

      reviews.push(review);
    });

    if (reviews.length === 0) {
      return;
    }

    var avgRating = Math.round((totalRating / reviewCount) * 10) / 10;

    var schemaData = {
      '@context': 'https://schema.org',
      '@type': 'Product',
      'name': document.title || '',
      'aggregateRating': {
        '@type': 'AggregateRating',
        'ratingValue': avgRating,
        'reviewCount': reviewCount,
        'bestRating': 5,
        'worstRating': 1
      },
      'review': reviews
    };

    var script = document.createElement('script');
    script.type = 'application/ld+json';
    script.id = 'reviews-by-url-schema';
    script.textContent = JSON.stringify(schemaData);
    document.head.appendChild(script);
  }

  // ===========================================================================
  // AJAX Pagination for "All Reviews" mode
  // ===========================================================================

  /**
   * Initializes AJAX pagination on a wrapper element.
   *
   * @param {HTMLElement} wrapper
   *   The .reviews-by-url-wrapper element with data-show-all="true".
   */
  function initAjaxPager(wrapper) {
    if (wrapper.dataset.ajaxPagerInitialized) {
      return;
    }
    wrapper.dataset.ajaxPagerInitialized = 'true';

    var listEl = wrapper.querySelector('[data-reviews-list]');
    var pagerNav = wrapper.querySelector('[data-reviews-pager]');
    if (!listEl || !pagerNav) {
      return;
    }

    var settings = drupalSettings.reviewsByUrl || {};
    var ajaxUrl = settings.ajaxUrl || '/reviews-by-url/ajax/page';
    var isLoading = false;

    /**
     * Load a page of reviews via AJAX.
     *
     @param {number} page
     *   The page number to load (1-based).
     */
    function loadPage(page) {
      if (isLoading) return;
      isLoading = true;

      // Add loading state.
      listEl.classList.add('reviews-by-url-list--loading');

      var xhr = new XMLHttpRequest();
      xhr.open('GET', ajaxUrl + '/' + page, true);
      xhr.setRequestHeader('Accept', 'application/json');
      xhr.onreadystatechange = function () {
        if (xhr.readyState !== 4) return;

        isLoading = false;
        listEl.classList.remove('reviews-by-url-list--loading');

        if (xhr.status !== 200) return;

        try {
          var data = JSON.parse(xhr.responseText);
        } catch (e) {
          return;
        }

        // Replace review cards.
        if (data.items_html && data.items_html.length > 0) {
          listEl.innerHTML = data.items_html.join('');
        }
        else {
          listEl.innerHTML = '';
        }

        // Update pager.
        if (data.pager) {
          updatePager(data.pager);
        }

        // Scroll to top of the reviews block smoothly.
        wrapper.scrollIntoView({ behavior: 'smooth', block: 'start' });

        // Re-run schema markup for new content.
        var oldSchema = document.getElementById('reviews-by-url-schema');
        if (oldSchema) {
          oldSchema.remove();
        }
        addSchemaMarkup(wrapper);
      };

      xhr.send();
    }

    /**
     * Updates the pager HTML from AJAX response data.
     *
     * @param {Object} pagerData
     *   Pager data with current, total, pages array.
     */
    function updatePager(pagerData) {
      var items = [];

      // First page.
      if (pagerData.current > 2) {
        items.push(createPagerItem('«', 1, 'reviews-by-url-pager__link--first', 'Первая страница'));
      }

      // Previous page.
      if (pagerData.current > 1) {
        items.push(createPagerItem('‹', pagerData.current - 1, 'reviews-by-url-pager__link--prev', 'Предыдущая страница'));
      }

      // Page numbers.
      if (pagerData.pages) {
        pagerData.pages.forEach(function (page) {
          if (page.is_ellipsis) {
            items.push('<li class="reviews-by-url-pager__item reviews-by-url-pager__item--ellipsis"><span class="reviews-by-url-pager__ellipsis">…</span></li>');
          }
          else if (page.is_current) {
            items.push('<li class="reviews-by-url-pager__item reviews-by-url-pager__item--active"><span class="reviews-by-url-pager__current" aria-current="page" data-page="' + page.number + '">' + page.number + '</span></li>');
          }
          else {
            items.push('<li class="reviews-by-url-pager__item"><a href="#" class="reviews-by-url-pager__link" data-page="' + page.number + '">' + page.number + '</a></li>');
          }
        });
      }

      // Next page.
      if (pagerData.current < pagerData.total) {
        items.push(createPagerItem('›', pagerData.current + 1, 'reviews-by-url-pager__link--next', 'Следующая страница'));
      }

      // Last page.
      if (pagerData.current < pagerData.total - 1) {
        items.push(createPagerItem('»', pagerData.total, 'reviews-by-url-pager__link--last', 'Последняя страница'));
      }

      var ul = pagerNav.querySelector('.reviews-by-url-pager__items');
      if (ul) {
        ul.innerHTML = items.join('');
      }
    }

    /**
     * Creates a pager link item HTML string.
     */
    function createPagerItem(label, page, extraClass, ariaLabel) {
      return '<li class="reviews-by-url-pager__item"><a href="#" class="reviews-by-url-pager__link ' + extraClass + '" data-page="' + page + '" aria-label="' + ariaLabel + '">' + label + '</a></li>';
    }

    // Delegate click events on pager links.
    pagerNav.addEventListener('click', function (e) {
      var link = e.target.closest('[data-page]');
      if (!link) return;

      e.preventDefault();
      var page = parseInt(link.getAttribute('data-page'), 10);
      if (page > 0) {
        loadPage(page);
      }
    });
  }

  // ===========================================================================
  // Drupal behavior
  // ===========================================================================

  Drupal.behaviors.reviewsByUrlBlock = {
    attach: function (context, settings) {
      var wrappers = context.querySelectorAll('.reviews-by-url-wrapper');
      if (wrappers.length === 0) {
        return;
      }

      wrappers.forEach(function (wrapper) {
        if (wrapper.dataset.reviewsAttached) {
          return;
        }
        wrapper.dataset.reviewsAttached = 'true';
        addSchemaMarkup(wrapper);

        // Initialize AJAX pager if in show_all mode.
        if (wrapper.dataset.showAll === 'true') {
          initAjaxPager(wrapper);
        }
      });
    }
  };

})(Drupal, drupalSettings);
