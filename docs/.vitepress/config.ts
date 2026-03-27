import { defineConfig } from 'vitepress'
import { configPlugin } from '../../../docs/plugins/configPlugin'
import { consoleCommandPlugin } from '../../../docs/plugins/consoleCommandPlugin'

export default defineConfig({
  base: '/imgix-asset-transformer/',
  srcDir: '.',
  title: 'Imgix Asset Transformer',
  description: 'Transforms images on the fly using Imgix\'s powerful image processing capabilities.',
  ignoreDeadLinks: true,

  srcExclude: [
    'node_modules/**',
    'plans/**',
  ],

  markdown: {
    config(md) {
      md.use(configPlugin)
      md.use(consoleCommandPlugin)
    },
  },

  themeConfig: {
    logo: '/logo.svg',

    nav: [
      { text: 'View on Plugin Store', link: 'https://plugins.craftcms.com/newism-imgix' },
      { text: 'All Plugins', link: 'https://plugins.newism.com.au/', target: '_self' },
    ],

    sidebar: [
      {
        text: 'Getting Started',
        items: [
          { text: 'Installation', link: '/installation' },
          { text: 'Configuration', link: '/configuration' },
          { text: 'How it works', link: '/how-it-works' },
        ],
      },
      {
        text: 'Template Guides',
        items: [
          { text: 'Image Transforms', link: '/image-transforms' },
          { text: 'Placeholder SVG', link: '/placeholder-svg' },
        ],
      },
      {
        text: 'Guides',
        items: [
          { text: 'Cache Purging', link: '/cache-purging' },
          { text: 'Caveats', link: '/caveats' },
          { text: 'Minimize Imgix Costs', link: '/minimize-imgix-costs' },
        ],
      },
      { text: 'Support', link: '/support' },
    ],

    socialLinks: [
      { icon: 'github', link: 'https://github.com/newism' },
      { icon: 'linkedin', link: 'https://www.linkedin.com/company/newism' },
    ],
  },
})
