# Cutover checklist (WP → Laravel)

## T-14 to T-7 days
- [ ] Full rehearsal of `wp:import-all` on staging from a fresh prod DB copy; counts match inventory.
- [ ] URL verification run on staging: 0 unexpected 404, no redirect chains.
- [ ] SEO parity check on top 50 pages (title, description, canonical, H1, schema, OG).
- [ ] Editors trained on the new admin; launch-day content freeze announced.
- [ ] Forms, emails, analytics/GTM, Search Console verification, cookie banner reproduced.
- [ ] Performance check (Lighthouse) ≥ the WordPress site.

## T-2 days
- [ ] Lower DNS TTL to 300 s.
- [ ] Backup WordPress files + DB; keep a read-only copy reachable on a private hostname.

## Launch day
- [ ] Content freeze on WordPress (maintenance mode for editors).
- [ ] Final delta import (`--since=` last rehearsal date), media rsync delta.
- [ ] Deploy Laravel with redirects, `robots.txt` (no leftover `Disallow: /` from staging!), sitemap.
- [ ] Switch DNS / vhost. Verify HTTPS, `www`/non-`www` and trailing-slash canonicalization.
- [ ] Run URL verification against production.
- [ ] Submit the new sitemap in Google Search Console; inspect a few key URLs.
- [ ] Check analytics is receiving data.

## T+1 to T+60 days
- [ ] Monitor 404s daily for two weeks (logs, redirects `hits`, Search Console "Not found"); add redirects as needed.
- [ ] Watch Search Console coverage and performance vs pre-launch baseline.
- [ ] Keep redirects in place permanently (at least one year).
- [ ] Decommission WordPress only after traffic is stable; remove its public access but keep a backup.
