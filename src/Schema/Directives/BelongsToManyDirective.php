<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Nuwave\Lighthouse\Support\Contracts\InterfaceFieldManipulator;

class BelongsToManyDirective extends RelationDirective implements FieldManipulator, InterfaceFieldManipulator
{
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Resolves a field through the Eloquent `BelongsToMany` relationship.
"""
directive @belongsToMany(
  """
  Specify the relationship method name in the model class,
  if it is named different from the field in the schema.
  """
  relation: String

  """
  Apply scopes to the underlying query.
  """
  scopes: [String!]

  """
  Allows to resolve the relation as a paginated list.
  """
  type: BelongsToManyType

  """
  Allow clients to query paginated lists without specifying the amount of items.
  Overrules the `pagination.default_count` setting from `lighthouse.php`.
  """
  defaultCount: Int

  """
  Limit the maximum amount of items that clients can request from paginated lists.
  Overrules the `pagination.max_count` setting from `lighthouse.php`.
  """
  maxCount: Int

  """
  Specify a custom type that implements the Edge interface
  to extend edge object.
  Only applies when using Relay style "connection" pagination.
  """
  edgeType: String
) on FIELD_DEFINITION

"""
Options for the `type` argument of `@belongsToMany`.
"""
enum BelongsToManyType {
    """
    Offset-based pagination, similar to the Laravel default.
    """
    PAGINATOR

    """
    Offset-based pagination like the Laravel "Simple Pagination", which does not count the total number of records.
    """
    SIMPLE

    """
    Cursor-based pagination, compatible with the Relay specification.
    """
    CONNECTION
}
GRAPHQL;
    }
}
