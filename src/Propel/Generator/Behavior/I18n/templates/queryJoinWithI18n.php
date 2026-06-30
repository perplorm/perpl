
/**
 * @deprecated Use {@see static::populateI18n()}
 *
 * @param string $locale Locale to use for the join condition, e.g. 'fr_FR'
 * @param string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
 *
 * @return $this
 */
public function joinWithI18n($locale = '<?php echo $defaultLocale ?>', $joinType = Criteria::LEFT_JOIN)
{
    return $this->populateI18n($locale, $joinType);
}

/**
 * Adds a JOIN clause to the query and hydrates the related I18n object.
 *
 * @param string $locale Locale to use for the join condition, e.g. 'fr_FR'
 * @param string $joinType Accepted values are null, 'left join', 'right join', 'inner join'. Defaults to left join.
 *
 * @return $this
 */
public function populateI18n($locale = '<?php echo $defaultLocale ?>', $joinType = Criteria::LEFT_JOIN)
{
    $this
        ->joinI18n($locale, null, $joinType)
        ->populateJoinedRelation('<?php echo $i18nRelationName ?>');

    // adjust RelationPopulator to additional join condition
    $this->relatedModelsToPopulate['<?php echo $i18nRelationName ?>']->overridePopulatesListOnTarget(false);

    return $this;
}
