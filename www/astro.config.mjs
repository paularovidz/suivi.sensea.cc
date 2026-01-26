import mdx from "@astrojs/mdx";
import react from "@astrojs/react";
import remarkToc from "remark-toc";
import AutoImport from "astro-auto-import";
import { defineConfig } from "astro/config";
import languagesJSON from "./src/config/language.json";
import rehypeExternalLinks from "rehype-external-links";
import remarkParseContent from "./src/lib/utils/remarkParseContent.ts";
import parseTomlToJson from "./src/lib/utils/parseTomlToJson.ts";
import tailwindcss from "@tailwindcss/vite";

const config = parseTomlToJson("./src/config/config.toml");
let supportedLanguages = [...languagesJSON.map((lang) => lang.languageCode)];

let {
  settings: {
    multilingual: {
      enable: multilingualEnable,
      show_default_lang_in_url,
      default_language,
      disable_language,
    },
  },
} = config;

// Remove default language from supportedLanguages
disable_language = multilingualEnable
  ? disable_language
  : supportedLanguages.map((lang) => lang !== "en" && lang).filter(Boolean);

// Filter out disabled languages from supportedLanguages
const locales = disable_language
  ? supportedLanguages.filter((lang) => !disable_language.includes(lang))
  : supportedLanguages;

// https://astro.build/config
export default defineConfig({
  site: config.site.base_url ? config.site.base_url : "http://examplesite.com",
  trailingSlash: config.site.trailing_slash ? "always" : "never",
  // Supprimer la configuration base qui cause le conflit avec les assets
  // base: config.site.base_url ? new URL(config.site.base_url).pathname : "/",
  build: {
    assets: "assets",
  },
  image: {
    experimentalLayout: "responsive",
    // Service d'images d'Astro temporairement réactivé pour la stabilité
    // service: {
    //   entrypoint: 'astro/assets/services/noop',
    // },
  },
  experimental: {
    responsiveImages: true,
  },
  i18n: {
    locales: locales,
    defaultLocale: default_language,
    routing: {
      redirectToDefaultLocale: show_default_lang_in_url ? false : true,
      prefixDefaultLocale: show_default_lang_in_url,
    },
  },
  integrations: [
    react(),
    AutoImport({
      imports: [
        "@/shortcodes/Button.astro",
        "@/shortcodes/Accordion.astro",
        "@/shortcodes/Notice.astro",
        "@/shortcodes/Tabs.astro",
        "@/shortcodes/Tab.astro",
        "@/shortcodes/Testimonial.astro",
        "@/shortcodes/ListCheck.astro",
        "@/shortcodes/CardGrid.astro",
        "@/shortcodes/Gallery.astro",
        "@/shortcodes/GalleryItem.astro",
        "@/shortcodes/Card.astro",
        "@/shortcodes/VideoInline.astro",
      ],
    }),
    mdx(),
  ],
  markdown: {
    rehypePlugins: [
      [
        rehypeExternalLinks,
        {
          rel: "noopener noreferrer nofollow",
          target: "_blank",
        },
      ],
    ],
    remarkPlugins: [
      remarkParseContent, // Parse markdown content and add classes in heading and loading="lazy" to images
      remarkToc,
    ],

    // Code Highlighter https://github.com/shikijs/shiki
    shikiConfig: {
      theme: "github-dark", // https://shiki.style/themes
      wrap: false,
    },
    extendDefaultPlugins: true,
  },
  vite: {
    plugins: [reloadOnTomlChange(), tailwindcss()],
    build: {
      rollupOptions: {
        output: {
          assetFileNames: (assetInfo) => {
            // Pas de hash pour les SVG (pour éviter les erreurs 500)
            if (assetInfo.name && assetInfo.name.endsWith('.svg')) {
              return 'assets/[name][extname]';
            }
            // Hash pour les autres assets
            return 'assets/[name].[hash][extname]';
          },
          chunkFileNames: "assets/[name].[hash].js",
          entryFileNames: "assets/[name].[hash].js",
        },
      },
    },
  },
});

// Trigger reload for when changing .toml files
function reloadOnTomlChange() {
  return {
    name: "reload-on-toml-change",
    handleHotUpdate({ file, server }) {
      if (file.endsWith(".toml")) {
        console.log("TOML file changed, triggering reload...");
        server.ws.send({ type: "full-reload" });
      }
    },
  };
}
