# OrderLine meta data
The shop (Atlantis) sends order lines to Waterfront which include additional non-generic data that can be different per product. This data is stored as metadata per order line in the database. The metadata is parsed into a DTO and serialized when persisted. As soon as the order will be processed the persisted data will be deserialized into the right DTO again for processing into a deployment.

## Modifying meta data

### Create a new DTO for a new product group type
This new DTO needs to extend [`MetaData`](../../../../../../src/Domain/Orders/DTO/CartOrderLines/MetaData/MetaData.php) and this DTO needs to be added to the Discriminator map in [`MetaData`](../../../../../../src/Domain/Orders/DTO/CartOrderLines/MetaData/MetaData.php).

### Add a property to an existing DTO
This new property needs to be nullable or a data migration needs to be executed to set a default value.

For example you want to add an extra option called `option1` with a default value of `false` for the DTO with the type `extension`. For PostgreSQL this would look like:
```PostgreSQL
UPDATE order_line_items
SET meta_data = jsonb_set(meta_data, ARRAY ['option1'],'false', true)
WHERE meta_data->>'type' = 'extension';
```

### Rename a property in an existing DTO
In order to rename a property and make the DTO still work with older data a data migration needs to be executed.

For example you want to rename the property `nme` to `name` for the DTO with the type `extension`. For PostgreSQL this would look like:
```PostgreSQL
UPDATE order_line_items
SET meta_data = meta_data - 'nme' || jsonb_build_object('name', meta_data->'nme')
WHERE meta_data ? 'nme'
AND meta_data->>'type' = 'extension';
```
