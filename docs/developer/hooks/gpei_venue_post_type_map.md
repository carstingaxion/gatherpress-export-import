# gpei_venue_post_type_map


Filters the merged venue post type mapping used during import.

This filter allows third-party code to add, remove, or modify the
mapping of source venue post type slugs to GatherPress venue post
type slugs. Works identically to `gpei_event_post_type_map` but
for venue/location post types. Source venue posts whose type appears
as a key in this map will be rewritten to `gatherpress_venue`,
and GatherPress will automatically create the corresponding shadow
taxonomy term in `_gatherpress_venue`.


source venue post type slugs and
values are GatherPress post type slugs.

## Example

Add support for a custom venue post type:

```php
    add_filter( 'gpei_venue_post_type_map', function ( array $map ): array {
        $map['my_venue_cpt'] = 'gatherpress_venue';
        return $map;
    } );
```

## Example

Remove the Events Manager location mapping if you handle it differently:

```php
    add_filter( 'gpei_venue_post_type_map', function ( array $map ): array {
        unset( $map['location'] );
        return $map;
    } );
```

## Parameters

- *`array<string,`* `string>` $venue_type_map Associative array where keys are

## Files

- [includes/classes/class-adapter-registry.php:205](https://github.com/carstingaxion/gatherpress-export-import/blob/main/includes/classes/class-adapter-registry.php#L205)
```php
apply_filters( 'gpei_venue_post_type_map', $this->venue_type_map )
```



[← All Hooks](Hooks.md)
