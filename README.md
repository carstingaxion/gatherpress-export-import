# Gatherpress | Export & Import

> *Is this real, GatherPress has no mechanism in place to allow the use of the default export and import tools?*
>
> https://github.com/GatherPress/gatherpress/issues/650

The code in this repo tries to fix this.

---

🐛 While this works fine locally with the [default wordpress-importer](https://github.com/WordPress/wordpress-importer), but unfortunately [WordPress playground uses the wordpress-importer v2](https://github.com/WordPress/wordpress-playground/issues/1358), [which doesn't have our relevant hook anymore](https://github.com/humanmade/WordPress-Importer/issues/93).

---

[Test it using WordPress' playground
](https://playground.wordpress.net/builder/builder.html?blueprint-url=https://raw.githubusercontent.com/carstingaxion/gatherpress-export-import/main/gatherpress-export-import-blueprint.json)
