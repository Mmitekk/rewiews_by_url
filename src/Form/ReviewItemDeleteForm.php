<?php

namespace Drupal\reviews_by_url\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a form for deleting Review Item entities.
 */
class ReviewItemDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t(
      'Вы уверены, что хотите удалить отзыв от "%label"?',
      ['%label' => $this->entity->label()]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('entity.review_item.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Удалить');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      $label = $this->entity->label();
      $this->entity->delete();

      $this->messenger()->addStatus($this->t(
        'Отзыв от "%label" удалён.',
        ['%label' => $label]
      ));
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t(
        'Ошибка при удалении отзыва: @message',
        ['@message' => $e->getMessage()]
      ));
    }

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
