# gpei_taxonomy_map


Filters the merged taxonomy mapping used during import.

This filter controls how source plugin taxonomy slugs are rewritten
to GatherPress or WordPress taxonomy slugs during the import process.
It is applied in two places:

1. In `rewrite_post_terms_taxonomy()` — rewrites the `domain` field
in per-post term assignments so terms are assigned to the correct
target taxonomy on the imported post.
2. In `intercept_term_creation()` — intercepts top-level `<wp:term>`
entries in the WXR file, creates the term in the target taxonomy,
and blocks creation in the source taxonomy.

Note: Taxonomy-based venue slugs (e.g., `event-venue` for Event
Organiser) should NOT be added here if they require special two-pass
handling — those are managed by the `Taxonomy_Venue_Handler` trait.


source taxonomy slugs and values
are target taxonomy slugs.

## Example

Map a custom source taxonomy to `gatherpress_topic`:

```php
    add_filter( 'gpei_taxonomy_map', function ( array $map ): array {
        $map['my_event_category'] = 'gatherpress_topic';
        return $map;
    } );
```

## Example

Redirect a source tag taxonomy to WordPress core `post_tag`:

```php
    add_filter( 'gpei_taxonomy_map', function ( array $map ): array {
        $map['custom_event_tags'] = 'post_tag';
        return $map;
    } );
```

## Parameters

- *`array<string,`* `string>` $taxonomy_map Associative array where keys are

## Files

- [includes/classes/class-migration.php:412](https://github.com/carstingaxion/gatherpress-export-import/blob/main/includes/classes/class-migration.php#L412)
```php
apply_filters( 'gpei_taxonomy_map', $this->taxonomy_map )
```



[← All Hooks](Hooks.md)
