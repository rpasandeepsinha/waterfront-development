# Refactoring in Waterfront

Here are some Waterfront-specific concerns that you need to know when
refactoring existing code.

## Moving files

* If you're moving a Model with the `auditable` trait to a different namespace,
  you need to add a mapping for the old fully qualified classname, so that audit
  logs that logged the old class name still work. See
 `app/Providers/AppServiceProvider.php` method `getOldMorphMapArray()` for
  examples.
