# Update requests

With update requests we sometimes have nullable properties with a default value set to null.
With those requests it would mean we would not update that spec/property with that request if these values are set/kept
to `null`, so the current value will be kept.
