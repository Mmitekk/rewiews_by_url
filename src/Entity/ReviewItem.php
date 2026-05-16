<?php

namespace Drupal\reviews_by_url\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedTrait;

/**
 * Defines the Review Item entity.
 *
 * @ContentEntityType(
 *   id = "review_item",
 *   label = @Translation("Review Item"),
 *   label_collection = @Translation("Review Items"),
 *   label_singular = @Translation("Review item"),
 *   label_plural = @Translation("Review items"),
 *   label_count = @PluralTranslation(
 *     singular = "@count отзыв",
 *     plural = "@count отзывов"
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\reviews_by_url\Entity\ReviewItemListBuilder",
 *     "form" = {
 *       "add" = "Drupal\reviews_by_url\Form\ReviewItemForm",
 *       "edit" = "Drupal\reviews_by_url\Form\ReviewItemForm",
 *       "delete" = "Drupal\reviews_by_url\Form\ReviewItemDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider"
 *     }
 *   },
 *   base_table = "review_item",
 *   admin_permission = "administer reviews by url",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "author_name",
 *     "uuid" = "uuid",
 *     "published" = "status",
 *     "weight" = "weight"
 *   },
 *   links = {
 *     "collection" = "/admin/config/content/reviews-by-url",
 *     "add-form" = "/admin/config/content/reviews-by-url/add",
 *     "edit-form" = "/admin/config/content/reviews-by-url/{review_item}/edit",
 *     "delete-form" = "/admin/config/content/reviews-by-url/{review_item}/delete"
 *   },
 *   field_ui_base_route = "entity.review_item.collection"
 * )
 */
class ReviewItem extends ContentEntityBase implements ReviewItemInterface {

  use EntityChangedTrait;
  use EntityPublishedTrait;

  /**
   * {@inheritdoc}
   */
  public function getAuthorName() {
    return $this->get('author_name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setAuthorName($name) {
    $this->set('author_name', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getReviewText() {
    return $this->get('review_text')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getReviewTextFormat() {
    return $this->get('review_text')->format;
  }

  /**
   * {@inheritdoc}
   */
  public function setReviewText($text, $format = NULL) {
    $this->set('review_text', [
      'value' => $text,
      'format' => $format ?? 'basic_html',
    ]);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRating() {
    return (int) $this->get('rating')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setRating($rating) {
    $this->set('rating', $rating);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCity() {
    return $this->get('city')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCity($city) {
    $this->set('city', $city);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getReviewDate() {
    return $this->get('review_date')->date;
  }

  /**
   * {@inheritdoc}
   */
  public function setReviewDate($date) {
    $this->set('review_date', $date);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getUrls() {
    $urls = [];
    foreach ($this->get('urls') as $item) {
      if (!empty($item->value)) {
        $urls[] = $item->value;
      }
    }
    return $urls;
  }

  /**
   * {@inheritdoc}
   */
  public function setUrls(array $urls) {
    $this->set('urls', $urls);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight() {
    return (int) $this->get('weight')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setWeight($weight) {
    $this->set('weight', $weight);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setReadOnly(TRUE);

    return $fields;
  }

}
