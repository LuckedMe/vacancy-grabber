export default class Page {
    constructor(
        public readonly id: string,
        public readonly slug: string | null = null,
        public readonly type: string | null = null,
        public readonly isHomepage: boolean | null = false,
        public readonly title: string | null = null,
        public readonly content: string | null = null,
        public readonly relatedArticlesTitle: string | null = null,
        //public readonly relatedArticles: string | null = null,
        public readonly sources: string | null = null,
        public readonly redirect: string | null = null,
        public readonly codeBlock: string | null = null,
        public readonly metaTitle: string | null = null,
        public readonly metaKeywords: string | null = null,
        public readonly metaDescription: string | null = null,
        public readonly ogTitle: string | null = null,
        public readonly ogDescription: string | null = null,
        public readonly ogImage: string | null = null,
        public readonly indexed: boolean | null = true,
        public readonly sitemapChangeFrequency: string | null = null,
        public readonly sitemapPriority: string | null = null,
    ) {
    }
}
