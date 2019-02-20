<?php

namespace Drupal\ui_patterns_fractal\Plugin\Deriver;

use Drupal\Component\Serialization\Yaml;
use Drupal\Component\Serialization\Json;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ui_patterns_library\Plugin\Deriver\LibraryDeriver;
use Drupal\Core\Render\Element;

/**
 * Class FractalDeriver.
 *
 * @package Drupal\ui_patterns_fractal\Deriver
 */
class FractalDeriver extends LibraryDeriver {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getFileExtensions() {
    // Configuration files can be formatted as JSON, YAML or as a JavaScript
    // file in the style of a CommonJS module that exports a configuration
    // object.
    // https://fractal.build/guide/core-concepts/configuration-files#configuration-file-formats
    return [
      "config.json",
      "config.yml",
      "config.js",
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getPatterns() {
    $patterns = [];
    foreach ($this->getDirectories() as $provider => $directory) {
      $directory = $directory . "/templates";
      foreach ($this->fileScanDirectory($directory) as $file_path => $file) {

        $absolute_base_path = dirname($file_path);
        $base_path = str_replace($this->root, "", $absolute_base_path);
        $id = basename(dirname($file_path));
        $definition = [];

        // We need a Twig file to have a valid pattern.
        if (!file_exists($absolute_base_path . "/" . $id . ".twig")) {
          continue;
        }

        // Parse definition file.
        $content = [];
        if (preg_match('/\.yml$/', $file_path)) {
          $content = file_get_contents($file_path);
          $content = Yaml::decode($content);
        }
        elseif (preg_match('/\.json$/', $file_path)) {
          $content = file_get_contents($file_path);
          $content = Json::decode($content);
        }
        elseif (preg_match('/\.js$/', $file_path)) {
          // TODO: Common JS parsing.
        }
        if (empty($content)) {
          continue;
        }

        // Set pattern meta.
        $definition['id'] = $id;
        $definition['base path'] = dirname($file_path);
        $definition['file name'] = $absolute_base_path;
        $definition['provider'] = $provider;

        // Set other pattern values.
        // The label is typically displayed in any UI navigation items that
        // refer to the component. Defaults to a title-cased version of the
        // component name if not specified.
        $label = isset($content['label']) ? $content['label'] : ucwords(urldecode($id));
        // The title of a component is typically what is displayed at the top
        // of any pages related to the component. Defaults to the same as the
        // label if not specified.
        $definition['label'] = isset($content['title']) ? $content['title'] : $label;
        $definition['description'] = $this->getDescription($content, $absolute_base_path);
        $definition['variants'] = $this->getVariants($content);
        // An array of tags to add to the component.
        // Can be used by plugins and tasks to filter components.
        $definition['tags'] = isset($content['tags']) ? $content['tags'] : [];
        $definition['fields'] = $this->getFields($content);
        $definition['libraries'] = $this->getLibraries($id, $absolute_base_path);

        // Override patterns behavior.
        // Use a stand-alone Twig file as template.
        $definition["use"] = $base_path . "/" . $id . ".twig";

        // Add pattern to collection.
        $patterns[] = $this->getPatternDefinition($definition);

      }
    }
    return $patterns;
  }

  /**
   *
   */
  private function getVariants($content) {

    if (!isset($content['variants']) || empty($content['variants'])) {
      return [];
    }

    // If you don't want to use the name 'default', you can specify the name of
    // the variant to be used as the default variant by using the default property
    // within the component's configuration.
    // https://fractal.build/guide/components/variants#the-default-variant
    $default_variant_key = isset($content['default']) ? $content['default'] : 'default';
    $variants = [
      $default_variant_key => [
        'label' => $default_variant_key,
      ],
    ];
    foreach ($content['variants'] as $variant) {
      $key = $variant['name'];
      $description = [];
      $variants[$key] = [
        'label' => isset($variant['label']) ? $variant['label'] : ucwords(str_replace('_', ' ', $key)),
      ];

      // No variant description in Fractal. No variant status in UI Patterns.
      // So, let's associate both.
      $status = isset($variant['status']) ? $variant['status'] : NULL;
      $description[] = $status ? $this->t('Status: @status.', ['@status' => $status]) : '';

      // This module's README tell the user to add a variant field with the
      // variant machine name as value in Fractal. We don't use it in UI
      // Patterns.
      if (isset($variant['context']) && !empty($variant['context'])) {
        unset($variant['context']['variant']);
      }

      if (isset($variant['context']) && !empty($variant['context'])) {
        // In Fractal, variants can have specific fields & values.
        // In UI Patterns, they can't. Let's tell the user about that.
        $fields = array_keys($variant['context']);
        $description[] = $this->t('Some Fractal fields are ignored: @fields.', ['@fields' => implode(',', $fields)]);
      }

      $variants[$key]['description'] = implode(' ', $description);

    }

    return $variants;
  }

  /**
   *
   */
  private function getFields($content) {

    // The context data to pass to the template when rendering previews.
    $fields = [];
    foreach ($content['context'] as $field => $preview) {
      // Fractal context fields has only a preview. No label, no type, no
      // description. However, we can guess the type and some infos about the
      // field.
      $description = '';
      $type = gettype($preview);
      if ($type === 'string') {
        $description = (filter_var($preview, FILTER_VALIDATE_URL) === $preview) ? $this->t('URL') : $description;
        $description = (filter_var($preview, FILTER_VALIDATE_EMAIL) === $preview) ? $this->t('E-mail') : $description;
        $description = ($preview !== strip_tags($preview)) ? $this->t('Markup') : $description;
      }
      if ($type === 'array') {
        $description = count(Element::properties($preview)) ? $this->t('Render array') : $description;
      }
      $fields[$field] = [
        // Underscores are allowed for plugins names.
        'label' => ucwords(str_replace('_', ' ', $field)),
        'preview' => $preview,
        'type' => $type,
        'description' => $description,
      ];
    }

    // Default variant is overriding values from context.
    // Context data defined at the component level will cascade down to all
    // the variants of that component.
    // If you don’t want to use the name ‘default’, you can specify the name of
    // the variant to be used as the default variant by using the default property.
    // https://fractal.build/guide/components/variants#the-default-variant
    $default = isset($content['default']) ? $content['default'] : "default";
    foreach ($content['variants'] as $variant) {
      if ($variant["name"] == $default) {
        foreach ($variant['context'] as $field => $preview) {
          $fields[$field] = [
            'label' => ucwords(str_replace('_', ' ', $field)),
            'preview' => $preview,
          ];
        }
      }
    }

    // Remove illegal attributes field.
    unset($fields['attributes']);

    return $fields;
  }

  /**
   *
   */
  private function getDescription($content, $base_path) {
    $description = '';
    // Any notes set here override content taken from the component’s README.md
    // file, if there is one. Accepts markdown.
    // https://fractal.build/guide/components/notes
    if (array_key_exists("notes", $content)) {
      $description = $content["notes"];
    }
    elseif (file_exists($base_path . "/README.md")) {
      $description = file_get_contents($base_path . "/README.md");
    }

    // We work with league/commonmark package because it is the one chosen by
    // https://www.drupal.org/project/markdown
    // TODO: Performance. Instanciate this object only once, instead of one for
    // each patterns.
    $converter = 'League\\CommonMark\\CommonMarkConverter';
    if (class_exists($converter)) {
      $converter = new $converter();
      $description = $converter->convertToHtml($description);
      // UI Patterns doesn't accept HTML in patterns descriptions.
      $description = strip_tags($description);
    }

    return $description;
  }

  /**
   *
   */
  private function getLibraries($id, $base_path) {
    $libraries = [];
    foreach (glob($base_path . "/*.css") as $filepath) {
      $filename = str_replace($base_path . "/", "", $filepath);
      $libraries[$id]["css"]["theme"][$filename] = [];
    }
    foreach (glob($base_path . "/*.js") as $filepath) {
      // We don't attach the CommonJS configuration file to the library.
      if (preg_match('/config\.js$/', $filepath)) {
        $continue;
      }
      $filename = str_replace($base_path . "/", "", $filepath);
      $libraries[$id]["js"][$filename] = [];
    }
    // The root level of libraries must be a list.
    if (!empty($libraries)) {
      $libraries = [
        $libraries,
      ];
    }
    return $libraries;
  }

}
