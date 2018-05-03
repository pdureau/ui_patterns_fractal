<?php

namespace Drupal\ui_patterns_fractal\Plugin\Deriver;

use Drupal\Component\Serialization\Yaml;
use Drupal\Component\Serialization\Json;
use Drupal\ui_patterns_library\Plugin\Deriver\LibraryDeriver;

/**
 * Class FractalDeriver.
 *
 * @package Drupal\ui_patterns_fractal\Deriver
 */
class FractalDeriver extends LibraryDeriver {

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
        # The label is typically displayed in any UI navigation items that
        # refer to the component. Defaults to a title-cased version of the
        # component name if not specified.
        $label = isset($content['label']) ? $content['label'] : ucwords(urldecode($id));
        # The title of a component is typically what is displayed at the top
        # of any pages related to the component. Defaults to the same as the
        # label if not specified.
        $definition['label'] = isset($content['title']) ? $content['title'] : $label;
        $definition['description'] = $this->getDescription($content, $absolute_base_path);
        # An array of tags to add to the component.
        # Can be used by plugins and tasks to filter components.
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
  private function getFields($content) {

    // The context data to pass to the template when rendering previews.
    $fields = [];
    foreach ($content['context'] as $field => $preview) {
      $fields[$field] = [
        "label" => $field,
        "preview" => $preview,
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
            "label" => $field,
            "preview" => $preview,
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
    // Any notes set here override content taken from the component’s README.md
    // file, if there is one. Accepts markdown.
    // https://fractal.build/guide/components/notes
    if (array_key_exists("notes", $content)) {
      // TODO: Markdown parsing.
      return $content["notes"];
    }
    if (file_exists($base_path . "/README.md")) {
      $md = file_get_contents($base_path . "/README.md");
      // TODO: Markdown parsing.
      return $md;
    }
    return "";
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
