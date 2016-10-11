## Kohana Polymorphic ORM Extension

This is a Kohana extension that adds polymorphic associations to the ORM.

It's just a simple file that extends Kohana_ORM

Relationships:

* morph_to - the equivalent of belongs_to, polymorphic
* morph_to_one_or_many - the equivalent of has_one or has_many, polymorphic
* morph_to_many_through - the equivalent of has_one or has_many, polymorphic

## Compatibility

* **Kohana 3.4** 
* **Kohana 3.3**

I haven't tested how it performs on lower versions, yet, so I can only add these for now.

( Will update after tests on lower versions )

## Installation

Download the ORM.php file and place it in the application/classes directory.
If you already have such a file, copy the contents of this file and place them accordingly.

That's it!

## Usage

### $morph_to and $morph_to_one_or_many

Let's assume we have an application that has a comment section ( Comment Model ) that belongs to several other models. For the sake of our example, those models with be Post and Website.

Let's create the comments table:

```sql
<?
  CREATE TABLE `comments` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `commentable_id` int(5) unsigned NOT NULL,
  `commentable_type` varchar(255) NOT NULL,
  `user_id` int(11) DEFAULT NOT NULL,
  `description` text NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
?>
```

Respectably, commentable_id and commentable_type will be the columns making the connection between the models. 
Now, we would assume a website to have many comments.

We do it like so:

```php
<?
  class Model_Website extends ORM
  {
      protected $morph_to_one_or_many = [
        'comments' => [
            'column' => 'commentable',
            'single' => false,
        ]
      ];
  }
?>
```

Single by default is false, but I would like to include it in the example so you know it exists.
Single defines if the relationship should return multiple results or a single one - in our case, we have multiple.

Now, when the website object is loaded and "comments" is called, it would query all the comments for that website.

Like so:

```php
<?
  $website = ORM::factory('Website')
              ->where('id', '=', 1)
              ->find();

  $comments = $website->comments->find_all();
?>
```

Chaining is allowed before "find_all()", so you could safely continue your query before getting your results:

```php
<?
  $comments_by_user_five = $website->comments->where('user_id', '=', 5)->find_all();
?>
```

Okay, so we have multiple polymorphic results. Let's do an example that loads only one result - by defining the single column true.
For that, we'll assume the Post model to have only one comment per post. I know it doesn't make sense, but for the sake of our example, let's do it like this.

```php
<?
  class Model_Post extends ORM
  {
      protected $morph_to_one_or_many = [
        'comment' => [
            'column' => 'commentable',
            'single' => true,
        ]
      ];
  }
?>
```

The Post model also loads comments by the "commentable" fields, of course. The only differences would be the single column - which you have to define as true in this case - and the name of relationship - I've changed it to singular so it makes more sense, now that we're loading only a single comment.

Invoke:

```php
<?
  $post = ORM::factory('Post')
              ->where('id', '=', 1)
              ->find();

  $comment = $post->comment;
?>
```

And that's how we load the comment. On the back stage, "find()" is called automatically, so you could directly access your comment's object properties on the fly:

```php
<?
  $comment = $post->comment;
  $comment_description = $comment->description; // either like this
  $comment_description = $post->comment->description; // or either like this
?>
```

And that's how you could work with $morph_to_one_or_many.

Now let's think about the reverse relationship - how could we load the Model that a comment belongs to?
For this example, we're going to use the structure we've used until now.

Let's say we have a bunch of comments and we need to load where they belong to.

We do it with a declaration in the Comment Model:

```php
<?
  class Model_Comment extends ORM
  {
      protected $morph_to = [
          'commentable_object' => 'commentable'
      ];
  }
?>
```

$morph_to is an array that takes a key parameter object_name and as value takes the column names used to define the relationship ( in this case - commentable, a reference to commentable_id and commentable_type, listed in the comments table)

And that's it! Now, we could load our related model like so:

```php
<?
  $comment = ORM::factory('Comment')
              ->where('id', '=', 1)
              ->find();

  $related_object = $comment->commentable;
?>
```

This is how we query the related model to the comment. The end results varies depending on which object you get - if you get a Website, it would load the website properties (sorry if it's obvious) and if it's Post, it would the post properties. You might like to think of an Interface that handles all the different cases, if you're planning on using this.

One last thing - in order for all of this to work, you'll need to fill in the database key relationships correctly.
In our case, we need to fill in commentable_id and commentable_type with an appropriate id and model.

Example:

```table
   id | commentable_id | commentable_type
    1               17           Website
    2               22              Post
```

We explicitly enter the Model name in the commentable_type column, along with it's id in the commentable_id column.

### $morph_to_many_through

This relationship is the equivalent of has_many_through in Kohana ORM, only polymorphic. And the tricky part is that one side of the relationship is the polymorphic association, while the other is standard foreign_key way.

Let's assume we have a Tag Model, a Knowledge_Base Model and a Project Model. Project and Knowledge_Base have many tags and the Tag model has many projects / knowledge_base entries.

As a standard has many through relationship, we create a pivot table:

```sql
<?
  CREATE TABLE `taggables` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `taggable_id` int(5) unsigned NOT NULL,
  `taggable_type` varchar(255) NOT NULL,
  `tag_id` int(11) DEFAULT NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
?>
```

We'll use the pivot table to connect between the models.
We connect the Knowledge_Base model to the Tag model like so:

```php
<?
  class Model_Knowledge_Base extends ORM
  {
      protected $morph_to_many_through = [
        'tags' => [
            'model' => 'Tag',
            'foreign_or_far_key' => 'tag_id',
            'column' => 'taggable',
            'pivot' => 'taggables',
            'polymorphic_start' => true,
        ]
      ];
  }
?>
```

Now these are a lot of declarations, so let me explain:
* **model** stands for the Model we're loading
* **foreign_or_far_key** is the far key in this declaration and it's used to connect to the tag model from the pivot table
* **column**  stands for the polymorphic pair (taggable_id and taggable_type in our example)
* **pivot** is the name of the pivot table
* **polymorphic_start** - this defines if the beginning of the relationship starts with a polymorphic association or the end. In this case, since we're connecting to the taggables table with the taggable columns, it should be true.

We load the tags for a certain KB entry like so:

```php
<?
  $kb = ORM::factory('Knowledge_Base')
              ->where('id', '=', 1)
              ->find();

  $tags = $kb->tags->find_all();
?>
```

Chaining is allowed, so you could run other query parameters before hitting find_all().

The reverse relationship goes as follows:

```php
<?
  class Model_Knowledge_Tag extends ORM
  {
      protected $morph_to_many_through = [
        'kb_entries' => [
            'model' => 'Knowledge_Base',
            'foreign_or_far_key' => 'tag_id',
            'column' => 'taggable',
            'pivot' => 'taggables',
            'polymorphic_start' => false,
        ]
      ];
  }
?>
```

This time, the Tag model connects to the pivot table via it's foreign_key and goes to the end model using the taggable association.

We get the kb_entries for a certain tag like this:

```php
<?
  $tag = ORM::factory('Tag')
              ->where('id', '=', 1)
              ->find();

  $kb_entries = $tag->kb_entries->find_all();
?>
```

That's it. :)