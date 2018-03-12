<?php

namespace Drupal\ui_patterns_fractal\Plugin\UiPatterns\Pattern;

use Drupal\ui_patterns\Plugin\PatternBase;

/**
 * The UI Pattern plugin.
 *
 * @UiPattern(
 *   id = "fractal",
 *   label = @Translation("Fractal Pattern"),
 *   description = @Translation("Pattern provided by a Fractal instance."),
 *   deriver = "\Drupal\ui_patterns_fractal\Plugin\Deriver\FractalDeriver"
 * )
 */
class FractalPattern extends PatternBase {

}
