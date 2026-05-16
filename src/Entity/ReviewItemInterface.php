<?php

namespace Drupal\reviews_by_url\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;

/**
 * Provides an interface for defining Review Item entities.
 */
interface ReviewItemInterface extends ContentEntityInterface, EntityPublishedInterface {

  /**
   * Gets the author name.
   *
   * @return string
   *   The author name.
   */
  public function getAuthorName();

  /**
   * Sets the author name.
   *
   * @param string $name
   *   The author name.
   *
   * @return \Drupal\reviews_by_url\Entity\ReviewItemInterface
   *   The called entity.
   */
  public function setAuthorName($name);

  /**
   * Gets the review text.
   *
   * @return string
   *   The review text.
   */
  public function getReviewText();

  /**
   * Gets the review text format.
   *
   * @return string
   *   The text format ID.
   */
  public function getReviewTextFormat();

  /**
   * Sets the review text.
   *
   * @param string $text
   *   The review text.
   * @param string|null $format
   *   The text format ID.
   *
   * @return \Drupal\reviews_by_url\Entity\ReviewItemInterface
   *   The called entity.
   */
  public function setReviewText($text, $format = NULL);

  /**
   * Gets the rating value.
   *
   * @return int
   *   The rating (1-5).
   */
  public function getRating();

  /**
   * Sets the rating value.
   *
   * @param int $rating
   *   The rating (1-5).
   *
   * @return \Drupal\reviews_by_url\Entity\ReviewItemInterface
   *   The called entity.
   */
  public function setRating($rating);

  /**
   * Gets the city.
   *
   * @return string
   *   The city.
   */
  public function getCity();

  /**
   * Sets the city.
   *
   * @param string $city
   *   The city.
   *
   * @return \Drupal\reviews_by_url\Entity\ReviewItemInterface
   *   The called entity.
   */
  public function setCity($city);

  /**
   * Gets the review date.
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime|null
   *   The review date.
   */
  public function getReviewDate();

  /**
   * Sets the review date.
   *
   * @param string $date
   *   The review date in YYYY-MM-DD format.
   *
   * @return \Drupal\reviews_by_url\Entity\ReviewItemInterface
   *   The called entity.
   */
  public function setReviewDate($date);

  /**
   * Gets the list of URLs this review item is assigned to.
   *
   * @return string[]
   *   Array of URL paths.
   */
  public function getUrls();

  /**
   * Sets the URLs for this review item.
   *
   * @param string[] $urls
   *   Array of URL paths.
   *
   * @return \Drupal\reviews_by_url\Entity\ReviewItemInterface
   *   The called entity.
   */
  public function setUrls(array $urls);

  /**
   * Gets the weight value.
   *
   * @return int
   *   The weight.
   */
  public function getWeight();

  /**
   * Sets the weight value.
   *
   * @param int $weight
   *   The weight.
   *
   * @return \Drupal\reviews_by_url\Entity\ReviewItemInterface
   *   The called entity.
   */
  public function setWeight($weight);

  /**
   * Gets the creation timestamp.
   *
   * @return int
   *   Creation timestamp.
   */
  public function getCreatedTime();

  /**
   * Sets the creation timestamp.
   *
   * @param int $timestamp
   *   Creation timestamp.
   *
   * @return \Drupal\reviews_by_url\Entity\ReviewItemInterface
   *   The called entity.
   */
  public function setCreatedTime($timestamp);

}
