<?php defined('SYSPATH') or die('No direct script access.');

class ORM extends Kohana_ORM 
{
    /**
     * Define polymorphic association
     * 
     * morph_to is an equivalent of belongs_to
     * only it retuns an associated Model
     *
     * @param $relationship - expects name of the $polymorphic_id and $polymorphic_type columns
     * Example:
     * in Model Event ( events table ) we declare
     * protected $morph_to = [
     *   'eventable_object' => 'eventable'
     * ];
     * this references the eventable_id and eventable_type columns 
     * in the events table and will return the associated object
     *
     * In this example, you could access directly 
     * the polymorphic object like so:
     * $event->eventable_object;
     * where $event is a loaded object from the Event model
     *
     * @var array $morph_to
     */
    protected $morph_to = [];

    /**
     * Define polymorphic association
     *
     * morph_one_or_many is an equivalent of
     * has_one or has_many, depending on it's
     * definition
     * @param array $relationship - expects column and single keys
     * @param string $relationship['model'] - model name
     * @param string $relationship['column'] - name of the $polymorphic_id 
     * and $polymorphic_type columns
     * @param bool $relationship['single'] - define the association as a
     * has_one (true) or has_many (false). It's false by default
     * Example:
     * in Model Website we declare:
     * protected $morph_one_or_many = [
     *    'upvotes' => [
     *      'model' => 'Upvote',
     *      'column' => 'upvoteable',
     *      'single' => false
     *    ],
     * ],
     * which will return all upvotes from the Upvote model
     *
     * @var array $morph_one_or_many
     */
    protected $morph_one_or_many = [];

    /**
     * Define polymorphic association
     *
     * morph_many_through is the equivalent of has_many_through,
     * only returning different models
     * @param array $relationship - expects column, pivot and polymorphable
     * @param string $relationship['model'] - model name
     * @param string $relationship['column'] - name of the $polymorphic_id 
     * and $polymorphic_type columns
     * @param bool $relationship['polymorphable'] - direction of the relationship
     * This column should be true if it ends with a polymorphic query
     * and false if it begins with one. Better explained in the example
     * @param string $relationship['pivot'] - name of the pivot table
     * @param string $relationship['foreign_or_far_key'] - foreign or far key, depending
     * on the direction of the relationship
     * Example:
     * In Tag Model we declare
     * protected $morph_many_through = [
     * 'project' => array(
     *       'polymorphable' => true,
     *       'column' => 'taggable',
     *       'pivot' => 'taggables',
     *   ),
     * ],
     * In this case, we have a Project and Tag Model,
     * and a pivot "taggables" with taggable columns (taggable_id 
     * and taggable_type). The pivot table also has a tag_id foreign key
     * We declare the association "polymorphable" => true when the Model
     * (Tag) connects to the pivot table normally (tag_id) and the pivot
     * connect to the last model polymorphably (taggable_id, taggable_type)
     * 
     * We declare the association "polymorphable" => false when the Model
     * (Project) connects to the pivot table polymorphably (taggable_id, taggable_type) 
     * and the pivot connects to the last model normally (tag_id)
     *
     * @var array $morph_many_through
    */
    protected $morph_many_through = [];

    public function __get($column) 
    {
        // skip if there's a predefined relationship
        if (! isset($this->_related[$column]))
        {
            // define morph_one_or_many
            if (isset($this->morph_one_or_many[$column])) 
            {
                // get associated columns
                $morped_column_id = $this->polymorphic_field_id('morph_one_or_many', $column);
                $morped_column_type = $this->polymorphic_field_object('morph_one_or_many', $column);
                $morped_model = $this->get_model('morph_one_or_many', $column);

                // fixate query
                $query = ORM::factory($morped_model)
                    ->where($morped_column_id, '=', $this->_object[$this->_primary_key])
                    ->where($morped_column_type, '=', inflector::singular($this->_table_name));

                // call find if association is set to single
                if (isset($this->morph_one_or_many[$column]['single']) &&
                    $this->morph_one_or_many[$column]['single'])
                {
                    $query = $query->find();
                }

                return $query;
            }
            // define morph_to
            else if (isset($this->morph_to[$column])) 
            {
                // get associated columns
                $morped_column_id = $this->polymorphic_field_id('morph_to', $column);
                $morped_column_type = $this->polymorphic_field_object('morph_to', $column);

                // return the referenced object
                return ORM::factory($this->{$morped_column_type})
                    ->where('id', '=', $this->{$morped_column_id})
                    ->find();
            }
            // define morph_many_through
            else if (isset($this->morph_many_through[$column])) 
            {
                // get associated columns
                $morped_column_id = $this->polymorphic_field_id('morph_many_through', $column);
                $morped_column_type = $this->polymorphic_field_object('morph_many_through', $column);
                $morped_model = $this->get_model('morph_many_through', $column);
                $pivot = $this->get_pivot($column);
                $foreign_or_far_key = $this->get_foreign_or_far('morph_many_through', $column);

                if ($this->is_polymorhapble($column))
                    return ORM::factory($morped_model)
                            ->join($pivot, 'LEFT')
                            ->on($pivot . '.' . $foreign_or_far_key, '=', inflector::singular($morped_model) . '.id')
                            ->where($pivot . '.' . $morped_column_id, '=', $this->_object[$this->_primary_key])
                            ->where($pivot . '.' . $morped_column_type, '=', inflector::singular($this->_table_name))
                            ->group_by(inflector::singular($morped_model) . '.id')
                            ->select(DB::expr(inflector::singular($morped_model) . '.*, COUNT('.$pivot . '.' . $morped_column_id .') as polymorhpic_count'))
                            ->having('polymorhpic_count', '>', 0);
                else 
                    return ORM::factory($morped_model)
                            ->join($pivot, 'LEFT')
                            ->on($pivot . '.' . $morped_column_id, '=', inflector::singular($morped_model) . '.id')
                            ->where($pivot . '.' . $foreign_or_far_key, '=', $this->_object[$this->_primary_key])
                            ->where($pivot . '.' . $morped_column_type, '=', $morped_model)
                            ->group_by(inflector::singular($morped_model) . '.id')
                            ->select(DB::expr(inflector::singular($morped_model) . '.*, COUNT('.$pivot . '.' . $morped_column_id .') as polymorhpic_count'))
                            ->having('polymorhpic_count', '>', 0);
                    
            }
        }

        return parent::__get($column);
    }

    /**
     * Get id column
     *
     * @param $key - relationship type
     * @return $column - name of the relationship
     * @return $polymorphic_id
     */
    protected function polymorphic_field_id($key, $column)
    {
        if (isset($this->{$key}[$column]['column']))
            return $this->{$key}[$column]['column'] .'_id';

        return $this->{$key}[$column] .'_id';
    }

    /**
     * Get type column
     *
     * @param $key - relationship type
     * @return $column - name of the relationship
     * @return $polymorphic_type
     */
    protected function polymorphic_field_object($key, $column)
    {
        if (isset($this->{$key}[$column]['column']))
            return $this->{$key}[$column]['column'] .'_type';

        return $this->{$key}[$column] .'_type';
    }

    /**
     * Get pivot table
     *
     * @param $column - name of the relationship
     * @return $pivot
     */
    protected function get_pivot($column)
    {
        return $this->morph_many_through[$column]['pivot'];
    }

    /**
     * Get pivot table
     *
     * @param $key - relationship type
     * @param $column - name of the relationship
     * @return $model - name of the model
     */
    protected function get_model($key, $column)
    {
        return $this->{$key}[$column]['model'];
    }

    /**
     * Get pivot table
     *
     * @param $key - relationship type
     * @param $column - name of the relationship
     * @return $foreign_key - relationship foreign key
     */
    protected function get_foreign_or_far($key, $column)
    {
        return $this->{$key}[$column]['foreign_or_far_key'];
    }

    /**
     * Check direction of morph_many_through relationship
     *
     * @param $column - name of the relationship
     * @return bool $polymorphic_start
     */
    protected function is_polymorhapble($column)
    {
        return $this->morph_many_through[$column]['polymorphic_start'];
    }
}