<?php

namespace Drupal\ui_patterns_fractal\Plugin\Deriver;

use Drupal\Component\Serialization\Yaml;
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
        if (preg_match('/\.json$/', $file_path)) {
          // TODO: JSON parsing.
        }
        if (preg_match('/\.js$/', $file_path)) {
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
        $definition['label'] = $content["title"];
        $definition['description'] = $this->getDescription($content, $base_path);
        $definition['tags'] = $content["tags"];
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
    // TODO: Default variant.
    // https://fractal.build/guide/components/variants#the-default-variant
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
