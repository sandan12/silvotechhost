import type { MetadataRoute } from 'next';
export const dynamic = 'force-static';
export default function robots(): MetadataRoute.Robots { return { rules: { userAgent: '*', allow: '/', disallow: ['/api/'] }, sitemap: 'https://silvotech.eu/sitemap.xml', host: 'https://silvotech.eu' }; }
