<?php

namespace Orion\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Support\Facades\App;
use Orion\Concerns\BuildsResponses;
use Orion\Concerns\HandlesAuthentication;
use Orion\Concerns\HandlesAuthorization;
use Orion\Concerns\InteractsWithHooks;
use Orion\Concerns\InteractsWithSoftDeletes;
use Orion\Contracts\ComponentsResolver;
use Orion\Contracts\Paginator;
use Orion\Contracts\ParamsValidator;
use Orion\Contracts\QueryBuilder;
use Orion\Contracts\RelationsResolver;
use Orion\Contracts\SearchBuilder;
use Orion\Exceptions\BindingException;

abstract class BaseController extends \Illuminate\Routing\Controller
{
    use AuthorizesRequests,
        DispatchesJobs,
        ValidatesRequests,
        HandlesAuthentication,
        HandlesAuthorization,
        InteractsWithHooks,
        InteractsWithSoftDeletes,
        BuildsResponses;

    /**
     * @var string $model
     */
    protected $model;

    /**
     * @var string $request
     */
    protected $request;

    /**
     * @var string $resource
     */
    protected $resource;

    /**
     * @var string|null $collectionResource
     */
    protected $collectionResource = null;

    /**
     * @var ComponentsResolver $componentsResolver
     */
    protected $componentsResolver;

    /**
     * @var ParamsValidator $paramsValidator
     */
    protected $paramsValidator;

    /**
     * @var RelationsResolver $relationsResolver
     */
    protected $relationsResolver;

    /**
     * @var Paginator $paginator
     */
    protected $paginator;

    /**
     * @var SearchBuilder $searchBuilder
     */
    protected $searchBuilder;

    /**
     * @var QueryBuilder $queryBuilder
     */
    protected $queryBuilder;

    /**
     * Controller constructor.
     *
     * @throws BindingException
     */
    public function __construct()
    {
        if (!$this->model) {
            throw new BindingException('Model is not defined for '.static::class);
        }

        $this->componentsResolver = App::makeWith(ComponentsResolver::class, [
            'resourceModelClass' => $this->resolveResourceModelClass()
        ]);
        $this->paramsValidator = App::makeWith(ParamsValidator::class, [
            'exposedScopes' => $this->exposedScopes(),
            'filterableBy' => $this->filterableBy(),
            'sortableBy' => $this->sortableBy()
        ]);
        $this->relationsResolver = App::makeWith(RelationsResolver::class, [
            'includableRelations' => $this->includes(),
            'alwaysIncludedRelations' => $this->alwaysIncludes()
        ]);
        $this->paginator = App::makeWith(Paginator::class, [
            'defaultLimit' => $this->limit()
        ]);
        $this->searchBuilder = App::makeWith(SearchBuilder::class, [
            'searchableBy' => $this->searchableBy()
        ]);
        $this->queryBuilder = App::makeWith(QueryBuilder::class, [
            'resourceModelClass' => $this->getModel(),
            'paramsValidator' => $this->paramsValidator,
            'relationsResolver' => $this->relationsResolver,
            'searchBuilder' => $this->searchBuilder
        ]);

        $this->resolveComponents();
        $this->bindComponents();
    }

    /**
     * Resolves request, resource and collection resource classes.
     */
    protected function resolveComponents(): void
    {
        if (!$this->request) {
            $this->setRequest($this->componentsResolver->resolveRequestClass());
        }

        if (!$this->resource) {
            $this->setResource($this->componentsResolver->resolveResourceClass());
        }

        if (!$this->collectionResource) {
            $this->setCollectionResource($this->componentsResolver->resolveCollectionResourceClass());
        }
    }

    /**
     * Binds resolved request class to the container.
     */
    protected function bindComponents(): void
    {
        $this->componentsResolver->bindRequestClass($this->getRequest());
    }

    /**
     * Authorize a given action for the current user.
     *
     * @param string $ability
     * @param array $arguments
     * @return \Illuminate\Auth\Access\Response
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function authorize($ability, $arguments = [])
    {
        $user = $this->resolveUser();

        return $this->authorizeForUser($user, $ability, $arguments);
    }

    /**
     * Get the map of resource methods to ability names.
     *
     * @return array
     */
    protected function resourceAbilityMap()
    {
        return [
            'index' => 'viewAny',
            'show' => 'view',
            'create' => 'create',
            'store' => 'create',
            'edit' => 'update',
            'update' => 'update',
            'destroy' => 'delete',
        ];
    }

    /**
     * The list of available query scopes.
     *
     * @return array
     */
    protected function exposedScopes()
    {
        return [];
    }

    /**
     * The attributes that are used for filtering.
     *
     * @return array
     */
    protected function filterableBy()
    {
        return [];
    }

    /**
     * The attributes that are used for searching.
     *
     * @return array
     */
    protected function searchableBy()
    {
        return [];
    }

    /**
     * The attributes that are used for sorting.
     *
     * @return array
     */
    protected function sortableBy()
    {
        return [];
    }

    /**
     * The relations that are allowed to be included together with a resource.
     *
     * @return array
     */
    protected function includes()
    {
        return [];
    }

    /**
     * The relations that are always included together with a resource.
     *
     * @return array
     */
    protected function alwaysIncludes()
    {
        return [];
    }

    /**
     * Default pagination limit.
     *
     * @return int
     */
    protected function limit()
    {
        return 15;
    }

    /**
     * @param string $modelClass
     * @return $this
     */
    public function setModel(string $modelClass): self
    {
        $this->model = $modelClass;

        return $this;
    }

    /**
     * @return string
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * @param string $requestClass
     * @return $this
     */
    public function setRequest(string $requestClass): self
    {
        $this->request = $requestClass;

        return $this;
    }

    /**
     * @return string
     */
    public function getRequest(): string
    {
        return $this->request;
    }

    /**
     * @param string $resourceClass
     * @return $this
     */
    public function setResource(string $resourceClass): self
    {
        $this->resource = $resourceClass;

        return $this;
    }

    /**
     * @return string
     */
    public function getResource(): string
    {
        return $this->resource;
    }

    /**
     * @param string|null $collectionResourceClass
     * @return $this
     */
    public function setCollectionResource(?string $collectionResourceClass): self
    {
        $this->collectionResource = $collectionResourceClass;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getCollectionResource(): ?string
    {
        return $this->collectionResource;
    }

    /**
     * @param ComponentsResolver $componentsResolver
     * @return $this
     */
    public function setComponentsResolver(ComponentsResolver $componentsResolver): self
    {
        $this->componentsResolver = $componentsResolver;

        return $this;
    }

    /**
     * @return ComponentsResolver
     */
    public function getComponentsResolver(): ComponentsResolver
    {
        return $this->componentsResolver;
    }

    /**
     * @param ParamsValidator $paramsValidator
     * @return $this
     */
    public function setParamsValidator(ParamsValidator $paramsValidator): self
    {
        $this->paramsValidator = $paramsValidator;

        return $this;
    }

    /**
     * @return ParamsValidator
     */
    public function getParamsValidator(): ParamsValidator
    {
        return $this->paramsValidator;
    }

    /**
     * @param RelationsResolver $relationsResolver
     * @return $this
     */
    public function setRelationsResolver(RelationsResolver $relationsResolver): self
    {
        $this->relationsResolver = $relationsResolver;

        return $this;
    }

    /**
     * @return RelationsResolver
     */
    public function getRelationsResolver(): RelationsResolver
    {
        return $this->relationsResolver;
    }

    /**
     * @param Paginator $paginator
     * @return $this
     */
    public function setPaginator(Paginator $paginator): self
    {
        $this->paginator = $paginator;

        return $this;
    }

    /**
     * @return Paginator
     */
    public function getPaginator(): Paginator
    {
        return $this->paginator;
    }

    /**
     * @param SearchBuilder $searchBuilder
     * @return $this
     */
    public function setSearchBuilder(SearchBuilder $searchBuilder): self
    {
        $this->searchBuilder = $searchBuilder;

        return $this;
    }

    /**
     * @return SearchBuilder
     */
    public function getSearchBuilder(): SearchBuilder
    {
        return $this->searchBuilder;
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @return $this
     */
    public function setQueryBuilder(QueryBuilder $queryBuilder): self
    {
        $this->queryBuilder = $queryBuilder;

        return $this;
    }

    /**
     * @return QueryBuilder
     */
    public function getQueryBuilder(): QueryBuilder
    {
        return $this->queryBuilder;
    }

    /**
     * Creates new Eloquent query builder of the model.
     *
     * @return Builder
     */
    public function newModelQuery(): Builder
    {
        return $this->getModel()::query();
    }

    /**
     * Retrieves model related to resource.
     *
     * @return string
     */
    abstract public function resolveResourceModelClass(): string;
}
