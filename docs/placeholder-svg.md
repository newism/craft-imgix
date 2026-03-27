---
outline: deep
---

# Placeholder SVG

The plugin provides a Twig global for generating transparent placeholder SVGs:

```twig
{# Generate a transparent SVG placeholder #}
{{ imgix.getPlaceholderSVG(800, 600) }}
```