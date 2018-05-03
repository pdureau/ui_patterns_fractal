# Fractal set-up

## Set new fractal project
``` 
sudo npm i -g @frctl/fractal
fractal new fractal
echo "fractal.web.set('builder.dest', path.join(__dirname, 'build'));" >> fractal.js
``` 

## Install the twig extension

Because Fractal has to produce Twig-based patterns to be used in Drupal.

``` 
cd fractal/
npm install --save @frctl/twig
echo "fractal.components.engine('@frctl/twig');" >> fractal.js
echo "fractal.components.set('ext', '.twig');" >> fractal.js
``` 

##  Display assets from component folder

Because Fractal has to keep the assets in the component folder.

``` 
vim components/_preview.twig
``` 
with:

``` 
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  {% for asset in _target.resources.assets %}
    {% if asset.isCSS %}
      <link rel="stylesheet" href="../raw/{{ _target.baseHandle }}/{{ asset.base }}">
    {% endif %}
    {% if asset.isJS %}
      <script src="../raw/{{ _target.baseHandle }}/{{ asset.base }}"></script>
    {% endif %}
  {% endfor %}
  <title>Preview Layout</title>
</head>
<body>
  {{ yield }}
</body>
</html>
``` 

# Using Fractal components in Drupal

Once ui_patterns_fractal module is installed, copy or link the Fractal's components/ folder into the 
templates/ folder of any Drupal module or theme, and clear all cache.

Check for the presence of Fractal patterns in Drupal /patterns page 
(provided by ui_patterns_library module, which is a dependency of 
ui_patterns_fractal)
