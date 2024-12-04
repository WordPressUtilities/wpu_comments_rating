# WPU Comments Rating

[![PHP workflow](https://github.com/WordPressUtilities/wpu_comments_rating/actions/workflows/php.yml/badge.svg 'PHP workflow')](https://github.com/WordPressUtilities/wpu_comments_rating/actions)

Allow users to rate in comments.

## Todo

- [ ] Make the rating system mandatory in comments.
- [ ] Edit rating in admin.
- [ ] Nicer UX for rating in front.

## Hooks

### wpu_comments_rating__post_types

- Select the post types where the rating is enabled.

### wpu_comments_rating__star_icon_vote

- HTML for the star icon.

### wpu_comments_rating__star_icon_empty

- HTML for the empty star icon when rating is displayed

## wpu_comments_rating__star_icon_full

- HTML for the full star icon when rating is displayed

### wpu_comments_rating__rating_position

- Position of the rating in the text comment : `before`, `after`. Any other value will hide the rating.

## Helpers

## wpu_comments_rating__get_rating_html

```php
echo wpu_comments_rating__get_rating_html();
```
