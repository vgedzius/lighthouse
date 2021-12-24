<?php

namespace Tests\Integration\Schema\Types;

use GraphQL\Type\Definition\Type;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\TypeRegistry;
use Tests\DBTestCase;
use Tests\Utils\Models\Team;
use Tests\Utils\Models\User;

class InterfaceTest extends DBTestCase
{
    public function testResolveInterfaceTypes(): void
    {
        // This creates one team with it
        factory(User::class)->create();

        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        interface Nameable {
            name: String!
        }

        type User implements Nameable {
            id: ID!
            name: String!
        }

        type Team implements Nameable {
            name: String!
        }

        type Query {
            namedThings: [Nameable!]! @field(resolver: "{$this->qualifyTestResolver('fetchResults')}")
        }
GRAPHQL;

        $result = $this->graphQL(/** @lang GraphQL */ '
        {
            namedThings {
                name
                ... on User {
                    id
                }
            }
        }
        ')->assertJsonStructure([
            'data' => [
                'namedThings' => [
                    [
                        'name',
                        'id',
                    ],
                    [
                        'name',
                    ],
                ],
            ],
        ]);

        $this->assertArrayNotHasKey('id', $result->json('data.namedThings.1'));
    }

    public function testConsidersRenamedModels(): void
    {
        // This creates one team with it
        factory(User::class)->create();

        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        interface Nameable {
            name: String!
        }

        type Foo implements Nameable @model(class: "User") {
            id: ID!
            name: String!
        }

        type Team implements Nameable {
            name: String!
        }

        type Query {
            namedThings: [Nameable!]! @field(resolver: "{$this->qualifyTestResolver('fetchResults')}")
        }
GRAPHQL;

        $result = $this->graphQL(/** @lang GraphQL */ '
        {
            namedThings {
                name
                ... on Foo {
                    id
                }
            }
        }
        ')->assertJsonStructure([
            'data' => [
                'namedThings' => [
                    [
                        'name',
                        'id',
                    ],
                    [
                        'name',
                    ],
                ],
            ],
        ]);

        $this->assertArrayNotHasKey('id', $result->json('data.namedThings.1'));
    }

    public function testDoesNotErrorOnSecondRenamedModel(): void
    {
        // This creates one team with it
        factory(User::class)->create();

        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        interface Nameable {
            name: String!
        }

        type Foo implements Nameable @model(class: "Team") {
            name: String!
        }

        type Bar implements Nameable @model(class: "User") {
            name: String!
        }

        type Query {
            namedThings: [Nameable!]! @field(resolver: "{$this->qualifyTestResolver('fetchResults')}")
        }
GRAPHQL;

        $this->expectNotToPerformAssertions();
        $this->graphQL(/** @lang GraphQL */ '
        {
            namedThings {
                name
            }
        }
        ');
    }

    public function testThrowsOnAmbiguousSchemaMapping(): void
    {
        // This creates one team with it
        factory(User::class)->create();

        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        interface Nameable {
            name: String!
        }

        type Foo implements Nameable @model(class: "User") {
            name: String!
        }

        type Team implements Nameable @model(class: "User") {
            name: String!
        }

        type Query {
            namedThings: [Nameable!]! @field(resolver: "{$this->qualifyTestResolver('fetchResults')}")
        }
GRAPHQL;

        $this->expectExceptionObject(
            new DefinitionException(
                TypeRegistry::unresolvableAbstractTypeMapping(User::class, ['Foo', 'Team'])
            )
        );
        $this->graphQL(/** @lang GraphQL */ '
        {
            namedThings {
                name
            }
        }
        ');
    }

    public function testThrowsOnNonOverlappingSchemaMapping(): void
    {
        // This creates one team with it
        factory(User::class)->create();

        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        interface Nameable {
            name: String!
        }

        type Team implements Nameable {
            name: String!
        }

        type NotPartOfInterface @model(class: "User") {
            id: String!
        }

        type Query {
            namedThings: [Nameable!]! @field(resolver: "{$this->qualifyTestResolver('fetchResults')}")
        }
GRAPHQL;

        $this->expectExceptionObject(
            new DefinitionException(
                TypeRegistry::unresolvableAbstractTypeMapping(User::class, [])
            )
        );
        $this->graphQL(/** @lang GraphQL */ '
        {
            namedThings {
                name
            }
        }
        ');
    }

    public function testUseCustomTypeResolver(): void
    {
        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        interface Nameable @interface(resolveType: "{$this->qualifyTestResolver('resolveType')}"){
            name: String!
        }

        type Guy implements Nameable {
            id: ID!
            name: String!
        }

        type Query {
            namedThings: Nameable @field(resolver: "{$this->qualifyTestResolver('fetchGuy')}")
        }
GRAPHQL;

        $this->graphQL(/** @lang GraphQL */ '
        {
            namedThings {
                name
                ... on Guy {
                    id
                }
            }
        }
        ')->assertJson([
            'data' => [
                'namedThings' => $this->fetchGuy(),
            ],
        ]);
    }

    public function testListPossibleTypes(): void
    {
        // This creates one team with it
        factory(User::class)->create();

        $this->schema = /** @lang GraphQL */ <<<GRAPHQL
        interface Nameable {
            name: String!
        }

        type User implements Nameable {
            id: ID!
            name: String!
        }

        type Team implements Nameable {
            name: String!
        }

        type Query {
            namedThings: [Nameable!]! @field(resolver: "{$this->qualifyTestResolver('fetchResults')}")
        }
GRAPHQL;

        $result = $this->graphQL(/** @lang GraphQL */ '
        {
            __schema {
                types {
                    kind
                    name
                    possibleTypes {
                        name
                    }
                }
            }
        }
        ');

        $interface = (new Collection($result->json('data.__schema.types')))
            ->firstWhere('name', 'Nameable');

        $this->assertCount(2, $interface['possibleTypes']);
    }

    public function testInterfaceManipulation()
    {
        $this->schema = /** @lang GraphQL */ <<<'GRAPHQL'
        interface HasPosts {
            posts: [Post!]! @paginate
        }
        type Post {
            id: ID!
        }
        type User implements HasPosts {
            id: ID!
            posts: [Post!]! @paginate
        }
        type Team implements HasPosts {
            posts: [Post!]! @paginate
        }
        type Query {
            foo: String
        }
GRAPHQL;

        $result = $this->graphQL(/** @lang GraphQL */ '
        {
            __type(name: "HasPosts") {
                name
                kind
                fields {
                    name
                    type {
                        name
                        kind
                    }
                }
            }
        }
        ');

        $this->assertEquals('HasPosts', $result->json('data.__type.name'));
        $this->assertEquals('INTERFACE', $result->json('data.__type.kind'));
        $this->assertEquals('PostPaginator', $result->json('data.__type.fields.0.type.name'));
    }

    public function fetchResults(): EloquentCollection
    {
        $users = User::all();
        $teams = Team::all();

        return $users->concat($teams);
    }

    public function resolveType(): Type
    {
        return app(TypeRegistry::class)->get('Guy');
    }

    /**
     * @return array<string, string>
     */
    public function fetchGuy(): array
    {
        return [
            'name' => 'bar',
            'id' => '1',
        ];
    }
}
