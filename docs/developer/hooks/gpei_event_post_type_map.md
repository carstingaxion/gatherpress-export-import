# gpei_event_post_type_map


Filters the merged event post type mapping used during import.

This filter allows third-party code to add, remove, or modify the
mapping of source event post type slugs to GatherPress post type
slugs. The map is built by merging all registered adapter definitions
and is consulted every time the WordPress Importer processes a post
during `wp_import_post_data_raw`. Any source post type present as a
key in this map will be rewritten to the corresponding value.


source event post type slugs and
values are GatherPress post type slugs.

## Example

Add support for a custom event post type:

```php
    add_filter( 'gpei_event_post_type_map', function ( array $map ): array {
        $map['my_custom_event'] = 'gatherpress_event';
        return $map;
    } );
```

## Example

Remove a built-in mapping to prevent automatic conversion:

```php
    add_filter( 'gpei_event_post_type_map', function ( array $map ): array {
        unset( $map['ai1ec_event'] );
        return $map;
    } );
```

## Parameters

- *`array<string,`* `string>` $event_type_map Associative array where keys are

## Files

- [includes/classes/class-adapter-registry.php:154](https://github.com/carstingaxion/gatherpress-export-import/blob/main/includes/classes/class-adapter-registry.php#L154)
```php
apply_filters( 'gpei_event_post_type_map', $this->event_type_map )
```



[← All Hooks](Hooks.md)
