<?php

namespace App\Http\Traits;

use Illuminate\Http\RedirectResponse;

trait CrudActions
{
    /**
     * Toggle active status (usabile per qualsiasi model con campo is_active)
     */
    public function toggleActive($model): RedirectResponse
    {
        // Controllo accesso specifico se esiste il metodo
        if (method_exists($this, 'checkAccess')) {
            $this->checkAccess($model);
        }

        $model->update(['is_active' => !$model->is_active]);

        $status = $model->is_active ? 'attivato' : 'disattivato';
        $entityName = $this->getEntityName($model);

        return redirect()->back()
            ->with('success', "{$entityName} \"{$model->name}\" {$status} con successo!");
    }

    /**
     * Generic destroy method
     */
    public function destroy($model): RedirectResponse
    {
        // Controllo accesso specifico se esiste il metodo
        if (method_exists($this, 'checkAccess')) {
            $this->checkAccess($model);
        }

        // Controllo se può essere eliminato
        if (method_exists($this, 'canBeDeleted') && !$this->canBeDeleted($model)) {
            return redirect()
                ->route($this->getIndexRoute())
                ->with('error', $this->getDeleteErrorMessage($model));
        }

        $name = $model->name;
        $entityName = $this->getEntityName($model);

        $model->delete();

        return redirect()
            ->route($this->getIndexRoute())
            ->with('success', "{$entityName} \"{$name}\" eliminato con successo!");
    }

    /**
     * Get entity name for messages (override in controllers)
     */
    protected function getEntityName($model): string
    {
        return class_basename($model);
    }

    /**
     * Get index route name (override in controllers)
     */
    protected function getIndexRoute(): string
    {
        return 'admin.' . strtolower(class_basename($this->getModelClass())) . 's.index';
    }

    /**
     * Get model class (override in controllers)
     */
    protected function getModelClass(): string
    {
        return 'Model';
    }

    /**
     * Get delete error message (override in controllers)
     */
    protected function getDeleteErrorMessage($model): string
    {
        return 'Impossibile eliminare questo elemento.';
    }
}
