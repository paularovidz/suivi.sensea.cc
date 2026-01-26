import { defineCollection, z } from "astro:content";
import parseTomlToJson from "./lib/utils/parseTomlToJson";

const config = parseTomlToJson("./src/config/config.toml");
const { comprendre_sens_folder, conseils_folder } = config.settings;

// Universal Page Schema
const page = z.object({
  title: z.string(),
  h1: z.string().optional(),
  date: z.date().optional(), // example date format 2022-01-01 or 2022-01-01T00:00:00+00:00 (Year-Month-Day Hour:Minute:Second+Timezone)
  description: z.string().optional(),
  image: z.string().optional(),
  draft: z.boolean().default(false),
  meta_title: z.string().optional(),
  meta_description: z.string().optional(),
  robots: z.string().optional(),
  exclude_from_sitemap: z.boolean().optional(),
  custom_slug: z.string().optional(),
  canonical: z.string().optional(),
  keywords: z.array(z.string()).optional(),
  disable_tagline: z.boolean().optional(),
  big_title: z.string().optional(),
  buttons: z.array(z.unknown()).optional(),
});

// Call to Action Button
const buttonSchema = z.object({
  enable: z.boolean(),
  label: z.string(),
  url: z.string(),
  rel: z.string().optional(),
  target: z.string().optional(),
});

// Pages collection schema
const pages_collection = defineCollection({
  schema: page,
});

// Post collection schema
const conseil_collection = defineCollection({
  schema: page.merge(
    z.object({
      categories: z.array(z.string()).default(["others"]),
      author: z.string().optional(),
      excerpt: z.string().optional(),
    }),
  ),
});

// Integration Collection
const comprendre_sens_collection = defineCollection({
  schema: page.merge(
    z.object({
      categories: z.array(z.string()).optional(),
      excerpt: z.string().optional(),
      cta_btn: buttonSchema.optional().nullable(),
      sections: z
        .array(
          z.object({
            title: z.string(),
            description: z.string(),
            category: z.string(),
          }),
        )
        .optional(),
      fields: z
        .array(
          z.object({
            name: z.string(),
            content: z.string(),
          }),
        )
        .optional(),
    }),
  ),
});

// Export collections
export const collections = {
  conseils: conseil_collection,
  [conseils_folder]: conseil_collection,
  [comprendre_sens_folder]: comprendre_sens_collection,

  pages: pages_collection,
  sections: defineCollection({}),
  faq: defineCollection({}),
  pricing: defineCollection({}),
  homepage: defineCollection({}),
  author: defineCollection({}),
  changelog: defineCollection({}),
};
