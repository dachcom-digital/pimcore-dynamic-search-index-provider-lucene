# Upgrade Notes

## Migrating from Version 1.x to Version 2.0.0

### Global Changes
- PHP8 return type declarations added: you may have to adjust your extensions accordingly

### New Features
- Index storage base path can be configured by using the `ds_lucene.index.base_path` which is `%kernel.project_dir%/var/bundles/DsLuceneBundle/index` by default