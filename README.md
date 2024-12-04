# WPU Comments Rating

[![PHP workflow](https://github.com/WordPressUtilities/wpu_comments_rating/actions/workflows/php.yml/badge.svg 'PHP workflow')](https://github.com/WordPressUtilities/wpu_comments_rating/actions)

Allow users to rate in comments.

## Hooks

### wpu_comments_rating__post_types

- Select the post types where the rating is enabled.

### wpu_comments_rating__star_icon_vote

- HTML for the star icon.

### wpu_comments_rating__rating_position

- Position of the rating in the text comment : `before`, `after`.

## Helpers

## wpu_comments_rating__get_rating_html

```php
echo wpu_comments_rating__get_rating_html();
```
