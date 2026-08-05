# WordPress.org directory assets

These files are source-controlled with the Git project but are not included in the installable plugin ZIP.

When staging a WordPress.org release, copy these files to the top-level `assets/` directory of the plugin's SVN checkout:

- `icon-128x128.png`
- `icon-256x256.png`

Do not place them in SVN `trunk/`, `tags/1.0.0/`, or an `assets/` directory inside the plugin package.

The 1024px master is retained under `source/` for future derivative work and should not be copied to WordPress.org SVN.
