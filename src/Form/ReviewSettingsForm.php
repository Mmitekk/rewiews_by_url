<?php

namespace Drupal\reviews_by_url\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Reviews by URL settings.
 */
class ReviewSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['reviews_by_url.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'reviews_by_url_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('reviews_by_url.settings');

    // ===== General settings =====
    $form['general'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Общие настройки'),
    ];

    $form['general']['block_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Заголовок блока отзывов'),
      '#description' => $this->t('Заголовок, отображаемый над блоком отзывов. Можно оставить пустым, чтобы не отображать заголовок.'),
      '#default_value' => $config->get('block_title') ?? 'Отзывы',
      '#maxlength' => 255,
    ];

    $form['general']['empty_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Сообщение при отсутствии отзывов'),
      '#description' => $this->t('Текст, отображаемый если для текущей страницы нет отзывов. Оставьте пустым, чтобы скрыть блок полностью.'),
      '#default_value' => $config->get('empty_message') ?? '',
      '#maxlength' => 255,
    ];

    $form['general']['all_reviews_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URL страницы «Все отзывы»'),
      '#description' => $this->t('Внутренний путь страницы со всеми отзывами (например, /vse-otzyvy). На эту страницу будет вести кнопка «Все отзывы». Оставьте пустым, чтобы скрыть кнопку.'),
      '#default_value' => $config->get('all_reviews_url') ?? '/vse-otzyvy',
      '#maxlength' => 255,
      '#placeholder' => '/vse-otzyvy',
    ];

    $form['general']['show_rating'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Показывать рейтинг (звёзды)'),
      '#description' => $this->t('Если включено, рядом с именем автора будет отображаться рейтинг в виде звёзд.'),
      '#default_value' => $config->get('show_rating') ?? TRUE,
    ];

    $form['general']['show_date'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Показывать дату отзыва'),
      '#description' => $this->t('Если включено, под именем автора будет отображаться дата отзыва.'),
      '#default_value' => $config->get('show_date') ?? TRUE,
    ];

    $form['general']['show_city'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Показывать город'),
      '#description' => $this->t('Если включено, рядом с именем автора будет отображаться город.'),
      '#default_value' => $config->get('show_city') ?? TRUE,
    ];

    // ===== Colors =====
    $form['colors'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Цвета и оформление'),
      '#description' => $this->t('Настройте цвета блока отзывов под дизайн вашего сайта. Оставьте поле пустым, чтобы использовать значение по умолчанию. Формат: HEX (например, #157fed).'),
    ];

    $form['colors']['color_accent'] = [
      '#type' => 'color',
      '#title' => $this->t('Акцентный цвет (синий)'),
      '#description' => $this->t('Основной цвет модуля: заголовок секции, бордюр карточки, ссылки.'),
      '#default_value' => $config->get('color_accent') ?? '#157fed',
      '#attributes' => ['placeholder' => '#157fed'],
    ];

    $form['colors']['color_author_text'] = [
      '#type' => 'color',
      '#title' => $this->t('Цвет имени автора'),
      '#description' => $this->t('Цвет текста имени автора отзыва.'),
      '#default_value' => $config->get('color_author_text') ?? '#333333',
      '#attributes' => ['placeholder' => '#333333'],
    ];

    $form['colors']['color_review_text'] = [
      '#type' => 'color',
      '#title' => $this->t('Цвет текста отзыва'),
      '#description' => $this->t('Основной цвет текста в блоке отзыва.'),
      '#default_value' => $config->get('color_review_text') ?? '#333333',
      '#attributes' => ['placeholder' => '#333333'],
    ];

    $form['colors']['color_border'] = [
      '#type' => 'color',
      '#title' => $this->t('Цвет разделительных линий'),
      '#description' => $this->t('Цвет горизонтальной линии-разделителя между именем и текстом отзыва.'),
      '#default_value' => $config->get('color_border') ?? '#eeeeee',
      '#attributes' => ['placeholder' => '#eeeeee'],
    ];

    $form['colors']['color_date_text'] = [
      '#type' => 'color',
      '#title' => $this->t('Цвет даты'),
      '#description' => $this->t('Цвет текста даты отзыва.'),
      '#default_value' => $config->get('color_date_text') ?? '#999999',
      '#attributes' => ['placeholder' => '#999999'],
    ];

    $form['colors']['color_star'] = [
      '#type' => 'color',
      '#title' => $this->t('Цвет звёзд рейтинга'),
      '#description' => $this->t('Цвет звёзд рейтинга (заполненных и пустых).'),
      '#default_value' => $config->get('color_star') ?? '#d89c11',
      '#attributes' => ['placeholder' => '#d89c11'],
    ];

    $form['colors']['color_card_bg'] = [
      '#type' => 'color',
      '#title' => $this->t('Фон карточки отзыва'),
      '#description' => $this->t('Цвет фона карточки каждого отзыва.'),
      '#default_value' => $config->get('color_card_bg') ?? '#ffffff',
      '#attributes' => ['placeholder' => '#ffffff'],
    ];

    $form['colors']['color_card_border'] = [
      '#type' => 'color',
      '#title' => $this->t('Цвет обводки карточки'),
      '#description' => $this->t('Цвет рамки карточки отзыва.'),
      '#default_value' => $config->get('color_card_border') ?? '#eeeeee',
      '#attributes' => ['placeholder' => '#eeeeee'],
    ];

    $form['colors']['color_section_bg'] = [
      '#type' => 'color',
      '#title' => $this->t('Фон секции'),
      '#description' => $this->t('Цвет фона всей секции отзывов.'),
      '#default_value' => $config->get('color_section_bg') ?? '#f2f2f2',
      '#attributes' => ['placeholder' => '#f2f2f2'],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('reviews_by_url.settings')
      ->set('block_title', $form_state->getValue('block_title'))
      ->set('empty_message', $form_state->getValue('empty_message'))
      ->set('all_reviews_url', $form_state->getValue('all_reviews_url'))
      ->set('show_rating', $form_state->getValue('show_rating'))
      ->set('show_date', $form_state->getValue('show_date'))
      ->set('show_city', $form_state->getValue('show_city'))
      ->set('color_accent', $form_state->getValue('color_accent'))
      ->set('color_author_text', $form_state->getValue('color_author_text'))
      ->set('color_review_text', $form_state->getValue('color_review_text'))
      ->set('color_border', $form_state->getValue('color_border'))
      ->set('color_date_text', $form_state->getValue('color_date_text'))
      ->set('color_star', $form_state->getValue('color_star'))
      ->set('color_card_bg', $form_state->getValue('color_card_bg'))
      ->set('color_card_border', $form_state->getValue('color_card_border'))
      ->set('color_section_bg', $form_state->getValue('color_section_bg'))
      ->save();

    // Invalidate cache so blocks pick up new colors immediately.
    \Drupal::service('cache_tags.invalidator')->invalidateTags(['review_item_list']);

    parent::submitForm($form, $form_state);
  }

}
