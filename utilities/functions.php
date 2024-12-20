<?php

if (!function_exists('sf_retrieve')) {
    /**
     * Given an array, returns a value located at index. For a nested value, specify an index string with parts
     * separated by a period character or an array of indices. If there is no value at the specified index, or the index is unreachable,
     * return the default value specified.
     *
     * @param  array         $collection The array from which a value should be retrieved.
     * @param  array|string  $index      A set of indices separated by periods or an array of indices.
     * @param  mixed         $default    A value to return if a value cannot otherwise be retrieved from the array.
     * @param  null|callable $callback   A callback to apply to the value which will be returned.
     * @return mixed
     */
    function sf_retrieve($collection, $index, $default = null, $callback = null)
    {
        if ((is_array($index) && false === empty($index)) || (is_string($index) && '' !== $index) || is_int($index)) {
            $index = is_array($index) ? $index : trim($index);
            $parts = is_array($index) ? $index : (is_string($index) ? explode('.', $index) : [$index]);

            // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition -- Assignment neceesary for while loop.
            while (is_array($collection) && null !== ($part = array_shift($parts))) {
                $collection = isset($collection[$part]) ? $collection[$part] : $default;
            }

            if (count($parts) > 0) {
                // If there are any indices left, then we obviously failed finding what we need
                $collection = $default;
            }
        } else {
            $collection = $default;
        }

        if (is_callable($callback)) {
            $collection = call_user_func($callback, $collection);
        }

        return $collection;
    }
}
