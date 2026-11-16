<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;

class UniqueEnabledEmail implements Rule
{
    protected $ignoreId;
    protected $table;
    protected $column;

    /**
     * Create a new rule instance.
     *
     * @param string $table
     * @param string $column
     * @param int|null $ignoreId
     */
    public function __construct($table = 'users', $column = 'email', $ignoreId = null)
    {
        $this->table = $table;
        $this->column = $column;
        $this->ignoreId = $ignoreId;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $query = DB::table($this->table)
            ->where($this->column, $value)
            ->where('enabled', 1);

        if ($this->ignoreId) {
            $query->where('id', '!=', $this->ignoreId);
        }

        return $query->count() === 0;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The email has already been taken.';
    }

    /**
     * Set the ID to ignore during validation.
     *
     * @param int $id
     * @return $this
     */
    public function ignore($id)
    {
        $this->ignoreId = $id;
        return $this;
    }
}