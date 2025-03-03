document.addEventListener("DOMContentLoaded", function() {
    'use strict';

    Array.prototype.forEach.call(document.querySelectorAll('.rating-container'), set_rating_container_methods);

    function set_rating_container_methods($container) {
        var $children = $container.querySelectorAll('.rating-item'),
            _nbChildren = $children.length;

        Array.prototype.forEach.call($children, function($child, index) {

            /* Add .hover class to items */
            $child.addEventListener('mouseenter', function() {
                for (var i = 0; i <= index; i++) {
                    $children[i].classList.add('hover');
                }
            });
            $child.addEventListener('mouseleave', function() {
                for (var i = 0; i < _nbChildren; i++) {
                    $children[i].classList.remove('hover');
                }
            });

            /* Add .selected class to items */
            $child.querySelector('input[type="radio"]').addEventListener('change', function() {
                var i;
                for (i = 0; i < _nbChildren; i++) {
                    $children[i].classList.remove('selected');
                }
                for (i = 0; i <= index; i++) {
                    $children[i].classList.add('selected');
                }
            });
        });
    }
});
