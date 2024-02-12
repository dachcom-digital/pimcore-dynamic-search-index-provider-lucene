# Upgrade Notes

## 2.0.3
- [BUGFIX] Fix type-hint in LuceneException constructor to match parent Exception class [#26](https://github.com/dachcom-digital/pimcore-dynamic-search-index-provider-lucene/issues/26)

## 2.0.2
- [BUGFIX] Pass locale to lucene index in multi search context

## 2.0.1
- [BUGFIX] SnowBallStemmingFilter uses not initialized locale [#15](https://github.com/dachcom-digital/pimcore-dynamic-search-index-provider-lucene/issues/15)
- [BUGFIX] basePath is scalar node, not boolean [@dpfaffenbauer](https://github.com/dachcom-digital/pimcore-dynamic-search-index-provider-lucene/pull/14)

## Migrating from Version 1.x to Version 2.0.0

### Global Changes
- PHP8 return type declarations added: you may have to adjust your extensions accordingly

### New Features
- Index storage base path can be configured by using the `ds_lucene.index.base_path` which is `%kernel.project_dir%/var/bundles/DsLuceneBundle/index` by default
