/**
 * @file
 * JavaScript behaviors for the Reviews by URL block.
 *
 * Provides Schema.org Review structured data for SEO.
 */

(function (Drupal, drupalSettings) {
  'use strict';

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
        // Try to parse Russian date format: DD.MM.YYYY
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

    // Build aggregate rating.
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

  /**
   * Attach behavior via Drupal's behavior system.
   */
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
      });
    }
  };

})(Drupal, drupalSettings);
