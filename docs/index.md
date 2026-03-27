---
title: Imgix Asset Transformer
description: Imgix-powered asset transforms for Craft CMS.
layout: home
hero:
    name: Imgix Asset Transformer
    text: For Craft CMS
    icon: /logo.svg
    tagline: Imgix-powered asset transforms for Craft CMS
    actions:
        - text: Get Started
          link: ./installation
        - text: View on Plugin Store
          link: https://plugins.craftcms.com/newism-imgix
          theme: alt
features:
    - title: Drop-in Replacement
      details: Replaces native image transforms, .srcset() methods, and Control Panel thumbnails. No template changes required.
      icon: 🔄
    - title: Imgix Rendering Parameters
      details: Add any Imgix rendering parameter via the imgix transform key — blur, monochrome, text overlays, and more.
      link: ./image-transforms#additional-imgix-parameters
      icon: 🎛️
    - title: Ratio-Based Transforms
      details: Aspect-ratio-locked images with the ratio option. Combine with width or height to control output size.
      link: ./image-transforms#ratio-based-transforms
      icon: 📐
    - title: Automatic Cache Purging
      details: Purges Imgix cache on asset save, move, replace, and delete. Queue-based at higher priority than Blitz.
      link: ./cache-purging
      icon: ⚡
    - title: Per-Volume Configuration
      details: Different Imgix sources per volume for multi-domain setups. Override any global setting on a per-volume basis.
      link: ./configuration#volume-overrides
      icon: 🗂️
    - title: URL Signing
      details: Secure image delivery via signingKey to prevent abuse of your Imgix source by third parties.
      link: ./configuration#imgix-settings
      icon: 🔒
    - title: Non-Image Assets Skipped
      details: PDFs, documents, and other non-image assets are skipped by default to reduce Imgix delivery costs.
      link: ./minimize-imgix-costs
      icon: 💰
    - title: PDF Rasterization
      details: Generate image thumbnails of PDF pages when enabled via skipTransform.
      link: ./caveats#pdf-files
      icon: 📄
    - title: Placeholder SVG
      details: Transparent SVG data URIs for preventing Cumulative Layout Shift while images load.
      link: ./placeholder-svg
      icon: 🖼️
    - title: Configuration Debugging
      details: Built-in settings page shows resolved config, volume overrides, and sample assets to verify your setup.
      link: ./configuration#debugging-configuration
      icon: 🔧
---
