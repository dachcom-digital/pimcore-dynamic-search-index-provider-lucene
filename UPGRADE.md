# Upgrade Notes

## 3.0.3
- [BUGFIX] Fix type-hint in LuceneException constructor to match parent Exception class [#26](https://github.com/dachcom-digital/pimcore-dynamic-search-index-provider-lucene/issues/26)

## 3.0.2
- [BUGFIX] Pass locale to lucene index in multi search context

## 3.0.1
- Fix ZendSearch Version Constraint

## Migrating from Version 2.x to Version 3.0.0

### Global Changes
- Recommended folder structure by symfony adopted

### Breaking Changes
- [TYPO] Renamed class `DsLuceneBundle\Event\AnalzyerEvent` to `DsLuceneBundle\Event\AnalyzerEvent`

***

2.x Upgrade Notes: https://github.com/dachcom-digital/pimcore-dynamic-search-index-provider-lucene/blob/2.x/UPGRADE.md
