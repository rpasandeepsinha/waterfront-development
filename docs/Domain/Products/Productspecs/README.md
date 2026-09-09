# Product specs

Product specs or specification can be added to 1 or more products. They are
primarily used to configure individual products. We can currently distinguish
two types.

1. **Config:** These are mainly config and settings, like for example
   `dns.visible_log_lines` or `services.technical_grace_period` Respectively,
   we can use this to set the number of visible DNS logs or the Grace period.
2. **Behavioral:** These are specifications that can be added to a product and
   serve primarily to control the behavior of the application, like for example
   `dns.is_premium`.

> *important* Currently, in some places  product specs are used in the code as a
> hardcoded string. It is important - especially with behavioral
> specifications - to include these in the enum
> `src/Domain/Products/Enums/ProductSpecName.php`.

## How are product specs added?
Customer support can add a product spec in Nova
`nova/resources/nova-product-spec-resources`. However, before this is possible,
development must go through the following steps:

1. If necessary (preferred) add the spec to the enum `src/Domain/Products/Enums/ProductSpecName.php`
2. Add the new spec to the config `config/product-specs.php`
3. Create a translation for the spec label in `resources/lang/waterfront-backend.json`
   (e.g. `product-spec.dns.visible_log_lines`)
4. Add a detailed explanation of the behavior of the spec as a translation in the same file
   (e.g. `product-spec.dns.visible_log_lines.explanation`)

> *caution* Currently the product specs are not type safe, in other words all
> values are stored and retrieved as a string.
> For boolean-like operation we can use the method `booleanSpecificationIsTrue`
> from the `src/Domain/Products/Repositories/ProductRepository.php`
