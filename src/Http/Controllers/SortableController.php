<?php

namespace KraenkVisuell\NovaSortable\Http\Controllers;

use KraenkVisuell\NovaSortable\Traits\HasSortableRows;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Nova;

class SortableController
{
    public function updateOrder(NovaRequest $request)
    {
        $validationResult = $this->validateRequest($request);
        $model = $validationResult->model;

        $resourceName = $request->route('resource');
        $resourceIds = $request->input('resourceIds');
        $viaResource = $request->input('viaResource');
        $viaResourceId = $request->input('viaResourceId');
        $viaRelationship = $request->input('viaRelationship');
        $relationshipType = $request->input('relationshipType');

        // Reverse the array if it is configured to order by DESC.
        if (HasSortableRows::getOrderByDirection($validationResult->sortable) === 'DESC') {
            $resourceIds = array_reverse($resourceIds);
        }

        // Relationship sorting
        if (! empty($viaResource)) {
            $resourceClass = Nova::resourceForKey($viaResource);
            if (empty($resourceClass)) {
                return response()->json(['resourceName' => 'invalid'], 400);
            }

            $modelClass = $resourceClass::$model;
            $model = $modelClass::find($viaResourceId);
            $relatedModels = $model->{$viaRelationship}()->findMany($resourceIds);
            if ($relatedModels->count() !== count($resourceIds)) {
                return response()->json(['resourceIds' => 'invalid'], 400);
            }

            // BelongsToMany
            if ($relationshipType === 'belongsToMany' || $relationshipType === 'morphToMany') {
                $relatedModels = $relatedModels->pluck(
                    $model->{$viaRelationship}()->getPivotAccessor()
                );
            }

            $relatedModel = $relatedModels->first();

            if (! empty($relatedModel)) {
                $orderColumnName = $relatedModel->determineOrderColumnName();
                $relatedKeyName = ($relationshipType === 'belongsToMany' || $relationshipType === 'morphToMany')
                    ? $model->{$viaRelationship}()->getRelatedPivotKeyName()
                    : $relatedModel->getKeyName();

                // Sort orderColumn values
                $sortedOrder = $relatedModels->pluck($orderColumnName)->sort()->values();
                $sortedOrder = $this->fixSortOrder($sortedOrder);

                // Validate if can be sorted
                if (method_exists($resourceClass, 'canSort')) {
                    $relatedModelsCopy = $relatedModels->values();

                    foreach ($resourceIds as $i => $id) {
                        $_model = $relatedModelsCopy->firstWhere($relatedKeyName, $id);
                        $relatedModelsCopy = $relatedModelsCopy->forget($relatedModelsCopy->search($_model));
                        $sortOrderNr = $sortedOrder[$i];

                        $canSort = $resourceClass::canSort($request, $_model);
                        if (! $canSort) {
                            $currentOrderNr = $_model->{$orderColumnName};

                            // canSort was false - check if the position changed
                            if ($currentOrderNr !== $sortedOrder[$i]) {
                                // Order changed - show error
                                return response()->json(['canNotReorder' => $id], 400);
                            }
                        }
                    }
                }

                $relatedModelsCopy = $relatedModels->values();
                foreach ($resourceIds as $i => $id) {
                    $_model = $relatedModelsCopy->firstWhere($relatedKeyName, $id);
                    $relatedModelsCopy = $relatedModelsCopy->forget($relatedModelsCopy->search($_model));
                    $sortOrderNr = $sortedOrder[$i];

                    $_model->{$orderColumnName} = $sortOrderNr;
                    $_model->save();
                }
            }

            return response('', 204);
        }

        // Regular ordering
        $resourceClass = Nova::resourceForKey($resourceName);
        if (empty($resourceClass)) {
            return response()->json(['resourceName' => 'invalid'], 400);
        }

        $modelClass = $resourceClass::$model;
        if (method_exists($modelClass, 'trashed')) {
            $models = $modelClass::withTrashed()->findMany($resourceIds);
        } else {
            $models = $modelClass::findMany($resourceIds);
        }
        if ($models->count() !== count($resourceIds)) {
            return response()->json(['resourceIds' => 'invalid'], 400);
        }

        $model = $models->first();
        $modelKeyName = $model->getKeyName();
        $orderColumnName = $model->determineOrderColumnName();

        // Sort orderColumn values
        $sortedOrder = $models->pluck($orderColumnName)->sort()->values();
        $sortedOrder = $this->fixSortOrder($sortedOrder);

        // Validate if can be sorted
        if (method_exists($resourceClass, 'canSort')) {
            foreach ($resourceIds as $i => $id) {
                $_model = $models->firstWhere($modelKeyName, $id);
                $canSort = $resourceClass::canSort($request, $_model);
                if (! $canSort) {
                    // canSort was false - check if the position changed
                    if ($_model->{$orderColumnName} !== $sortedOrder[$i]) {
                        // Order changed - show error
                        return response()->json(['canNotReorder' => $id], 400);
                    }
                }
            }
        }

        // Continue with reorder
        foreach ($resourceIds as $i => $id) {
            $_model = $models->firstWhere($modelKeyName, $id);
            $_model->{$orderColumnName} = $sortedOrder[$i];
            $_model->save();
        }

        return response('', 204);
    }

    public function changePosition(NovaRequest $request)
    {
        $validationResult = $this->validateRequest($request);
        $model = $validationResult->model;
        $orderColumn = $model->determineOrderColumnName();
         
        $newPostion = intval($request->newPosition);
        $oldPostion = intval($model->{$orderColumn});
        $moveUp = $newPostion < $oldPostion;

        if ($moveUp) {
            while ($model->{$orderColumn} > $newPostion) {
                $model->moveOrderUp();
            }
        } else {
            while ($model->{$orderColumn} < $newPostion) {
                $model->moveOrderDown();
            }
        }

        return response('', 204);
    }

    public function moveToStart(NovaRequest $request)
    {
        
        $validationResult = $this->validateRequest($request);
        
        $position = HasSortableRows::getOrderByDirection($validationResult->sortable) !== 'DESC' 
            ? 1 
            : $validationResult->model->getHighestOrderNumber();
       
        $this->changePosition($request->merge(['newPosition' => $position]));
    }

    public function moveToEnd(NovaRequest $request)
    {
        $validationResult = $this->validateRequest($request);
        
        $position = HasSortableRows::getOrderByDirection($validationResult->sortable) !== 'DESC' 
            ? $validationResult->model->getHighestOrderNumber()
            : 1;

            $this->changePosition($request->merge(['newPosition' => $position]));
    }

    protected function validateRequest(NovaRequest $request)
    {
        $request->validate([
            'resourceId' => 'present',
            'resourceIds' => 'required_if:resourceId,null',
            'relatedResource' => 'present',
            'relationshipType' => 'present',
            'viaRelationship' => 'present',
            'viaResource' => 'present',
            'viaResourceId' => 'present',
        ]);

        return HasSortableRows::getSortability($request);
    }

    protected function fixSortOrder($sortOrder)
    {
        $improvedSortedOrder = [];
        $previousSortOrderNr = null;
        foreach ($sortOrder as $i => $orderNr) {
            $sortOrderNr = $orderNr ?? ((int) $previousSortOrderNr) + 1;
            if ($sortOrderNr === $previousSortOrderNr) {
                $sortOrderNr += 1;
            }
            $previousSortOrderNr = $sortOrderNr;
            $improvedSortedOrder[$i] = $sortOrderNr;
        }

        return $improvedSortedOrder;
    }
}
