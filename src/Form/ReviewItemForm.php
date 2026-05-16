<?php

namespace Drupal\reviews_by_url\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Form controller for Review Item edit forms.
 */
class ReviewItemForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\reviews_by_url\Entity\ReviewItemInterface $entity */
    $entity = $this->entity;

    // Improve the URLs field widget with better description.
    if (isset($form['urls'])) {
      $form['urls']['widget'][0]['value']['#description'] = $this->t(
        'Введите внутренний путь страницы (например, /catalog/metallocherepitsa) или <code>&lt;front&gt;</code> для главной страницы. ' .
        'Нажмите кнопку "Добавить ещё" для добавления дополнительных URL. ' .
        'Поддерживаются пути категорий таксономии, нод и представлений, ' .
        'псевдонимы URL (алиасы) и маски путей (например, /catalog/*).'
      );
      $form['urls']['widget'][0]['value']['#placeholder'] = '/catalog/metallocherepitsa';

      // Add autocomplete route for URL suggestions.
      $form['urls']['widget'][0]['value']['#attributes']['data-drupal-review-url'] = 'true';
    }

    // Move weight to a more logical position.
    if (isset($form['weight'])) {
      $form['weight']['#group'] = 'advanced';
      $form['weight']['widget'][0]['value']['#description'] = $this->t(
        'Меньшее значение = выше в списке. Отзывы сортируются по весу, затем по ID.'
      );
    }

    // Add helpful tips section.
    $form['review_tips'] = [
      '#type' => 'details',
      '#title' => $this->t('Подсказки по использованию'),
      '#open' => FALSE,
      '#weight' => 100,
      'tips' => [
        '#markup' => '<ul>' .
          '<li>' . $this->t('Укажите имя автора отзыва — это будет отображаться как заголовок карточки.') . '</li>' .
          '<li>' . $this->t('Рейтинг — это число от 1 до 5 (звёзды). По умолчанию 5.') . '</li>' .
          '<li>' . $this->t('Город — необязательное поле, отображается рядом с именем автора.') . '</li>' .
          '<li>' . $this->t('Дата отзыва — если не указана, используется дата создания.') . '</li>' .
          '<li>' . $this->t('Укажите один или несколько URL страниц, на которых должен отображаться данный отзыв.') . '</li>' .
          '<li>' . $this->t('URL должен быть внутренним путём сайта, например: <code>/catalog/metallocherepitsa</code>') . '</li>' .
          '<li>' . $this->t('<strong>Главная страница:</strong> используйте <code>&lt;front&gt;</code> — это работает независимо от того, что является главной (нода, представление, кастомный путь).') . '</li>' .
          '<li>' . $this->t('Вы можете использовать как системные пути (<code>/taxonomy/term/5</code>), так и псевдонимы (<code>/catalog/metallocherepitsa</code>).') . '</li>' .
          '<li>' . $this->t('Маски путей: <code>/catalog/*</code> — выведет отзывы на всех подстраницах каталога.') . '</li>' .
          '<li>' . $this->t('Один отзыв можно привязать к нескольким страницам, добавив несколько URL.') . '</li>' .
          '<li>' . $this->t('Неопубликованные отзывы не отображаются на сайте.') . '</li>' .
          '<li>' . $this->t('Блок отзывов должен быть размещён в Схеме блоков (Структура → Схема блоков).') . '</li>' .
          '</ul>',
      ],
    ];

    return $form;
  }

  /**
   * Extracts URL values from the form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return string[]
   *   Array of raw URL strings from the form.
   */
  protected function extractUrlValues(FormStateInterface $form_state) {
    $raw = $form_state->getValue(['urls']);
    $urls = [];

    // If the value has the 'widget' key, drill into it.
    if (is_array($raw) && isset($raw['widget'])) {
      $raw = $raw['widget'];
    }

    if (!is_array($raw)) {
      return $urls;
    }

    foreach ($raw as $delta => $item) {
      // Skip non-array items (metadata keys like '_weight', 'add_more', etc.).
      if (!is_array($item)) {
        continue;
      }
      $value = $item['value'] ?? '';
      if (!empty($value) && is_string($value)) {
        $urls[$delta] = $value;
      }
    }

    return $urls;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Validate rating.
    $rating = $form_state->getValue(['rating', 0, 'value']);
    if (!empty($rating)) {
      $rating = (int) $rating;
      if ($rating < 1 || $rating > 5) {
        $form_state->setErrorByName(
          'rating][0][value',
          $this->t('Рейтинг должен быть от 1 до 5.')
        );
      }
    }

    // Validate URLs format.
    $urls = $this->extractUrlValues($form_state);

    foreach ($urls as $delta => $url_value) {
      $url_value = trim($url_value);
      if (empty($url_value)) {
        continue;
      }

      // Allow <front> token as-is.
      if ($url_value === '<front>') {
        continue;
      }

      // Normalize URL - ensure it starts with /.
      if (!str_starts_with($url_value, '/')) {
        $url_value = '/' . $url_value;
      }

      // Check for external URLs (not allowed).
      if (str_starts_with($url_value, 'http://') || str_starts_with($url_value, 'https://')) {
        $form_state->setErrorByName(
          'urls][widget][' . $delta . '][value',
          $this->t('Укажите внутренний путь сайта (без домена), например: /catalog/metallocherepitsa или &lt;front&gt;')
        );
        continue;
      }

      // Basic URL path validation: allow valid path characters and wildcards.
      if (!preg_match('#^/[a-zA-Z0-9_\-/{}.*?<>]+$#', $url_value)) {
        $form_state->setErrorByName(
          'urls][widget][' . $delta . '][value',
          $this->t('Некорректный URL: @url', ['@url' => $url_value])
        );
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Normalize URLs before the entity is populated from form values.
    $raw = $form_state->getValue(['urls']);

    if (is_array($raw) && isset($raw['widget'])) {
      foreach ($raw['widget'] as $delta => &$item) {
        // Skip non-array items (metadata keys).
        if (!is_array($item) || !isset($item['value']) || !is_string($item['value'])) {
          continue;
        }

        $url = trim($item['value']);
        if (empty($url)) {
          continue;
        }

        // Don't normalize <front>.
        if ($url === '<front>') {
          continue;
        }

        // Ensure URL starts with /.
        if (!str_starts_with($url, '/')) {
          $url = '/' . $url;
        }

        // Remove trailing slash (except for root).
        if ($url !== '/' && str_ends_with($url, '/')) {
          $url = rtrim($url, '/');
        }

        $item['value'] = $url;
      }
      // Unset reference to avoid issues.
      unset($item);

      $form_state->setValue(['urls'], $raw);
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;

    // Normalize URLs on the entity directly as a safety net.
    $urls = $entity->getUrls();
    $normalized = [];
    foreach ($urls as $url) {
      $url = trim($url);
      if (empty($url)) {
        continue;
      }
      // Don't normalize <front>.
      if ($url === '<front>') {
        $normalized[] = $url;
        continue;
      }
      if (!str_starts_with($url, '/')) {
        $url = '/' . $url;
      }
      if ($url !== '/' && str_ends_with($url, '/')) {
        $url = rtrim($url, '/');
      }
      $normalized[] = $url;
    }
    $entity->setUrls($normalized);

    $status = $entity->save();

    $message_args = ['%label' => $entity->getAuthorName()];

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('Отзыв от "%label" создан.', $message_args));
        break;

      default:
        $this->messenger()->addStatus($this->t('Отзыв от "%label" обновлён.', $message_args));
    }

    $form_state->setRedirectUrl(Url::fromRoute('entity.review_item.collection'));
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);

    // Add a "Save and add another" action.
    $actions['save_and_add'] = [
      '#type' => 'submit',
      '#value' => $this->t('Сохранить и добавить ещё'),
      '#submit' => ['::submitForm', '::saveAndAddAnother'],
      '#weight' => 5,
    ];

    return $actions;
  }

  /**
   * Custom submit handler: save and redirect to add form.
   */
  public function saveAndAddAnother(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;

    // Normalize URLs on the entity.
    $urls = $entity->getUrls();
    $normalized = [];
    foreach ($urls as $url) {
      $url = trim($url);
      if (empty($url)) {
        continue;
      }
      if ($url === '<front>') {
        $normalized[] = $url;
        continue;
      }
      if (!str_starts_with($url, '/')) {
        $url = '/' . $url;
      }
      if ($url !== '/' && str_ends_with($url, '/')) {
        $url = rtrim($url, '/');
      }
      $normalized[] = $url;
    }
    $entity->setUrls($normalized);

    $entity->save();

    $this->messenger()->addStatus($this->t(
      'Отзыв от "%label" создан. Вы можете добавить ещё один.',
      ['%label' => $entity->getAuthorName()]
    ));

    $form_state->setRedirectUrl(Url::fromRoute('entity.review_item.add_form'));
  }

}
