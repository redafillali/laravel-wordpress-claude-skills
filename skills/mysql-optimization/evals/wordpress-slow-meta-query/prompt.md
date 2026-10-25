---
tags: [skill-creator]
max_turns: 20
timeout_seconds: 600
allowed_tools: [Read, Glob, Grep, Skill, Write, Edit, Bash]
---

our wordpress woocommerce site is super slow, mysql at 90% cpu on the VPS. slow log shows this one a lot (2-4 sec each):

SELECT SQL_CALC_FOUND_ROWS wp_posts.ID FROM wp_posts INNER JOIN wp_postmeta ON ( wp_posts.ID = wp_postmeta.post_id ) INNER JOIN wp_postmeta AS mt1 ON ( wp_posts.ID = mt1.post_id ) WHERE 1=1 AND ( ( wp_postmeta.meta_key = '_stock_status' AND wp_postmeta.meta_value = 'instock' ) AND ( mt1.meta_key = 'brand' AND mt1.meta_value = 'Bosch' ) ) AND wp_posts.post_type = 'product' AND (wp_posts.post_status = 'publish') GROUP BY wp_posts.ID ORDER BY wp_posts.post_date DESC LIMIT 0, 24

wp_postmeta has 4.8 million rows, wp_posts 120k. also autoload in wp_options is 3.2MB. what do i do, step by step
