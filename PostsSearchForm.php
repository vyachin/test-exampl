<?php

namespace api\versions\v4_3\models\posts\forms;

use common\models\babydayka\posts\PostView;
use yii\base\Model;

use api\versions\v4_3\models\posts\PostQuery;
use api\versions\v4_3\models\posts\Post;
use api\versions\v4_3\models\posts\PostCategory;
use api\versions\v4_3\models\user\User;

/**
 * Class ModuleSearchForm
 *
 * @property-read PostViewForm[] $models
 * @property-read string $babiesTerms
 * @property-read User $user
 */
class PostsSearchForm extends Model
{
    /**
     * @type integer                ID  пользователя
     */
    public $userId = null;

    /**
     * @type array                  Ids категорий
     */
    public $postCategoryIds;

    /**
     * @type string                 Язык
     */
    public $language = "ru";

    /**
     * @type string                 Строка поиска
     */
    public $searchString = null;

    /**
     * @type array                  Массив строк, в который разбивается строка поиска если в ней есть пробелы
     */
    private $partsOfSearch = [];

    /**
     * @var null
     */
    public $favoriteOnly = null;

    /**
     * @var string[]                Attach to response additional relations e.g. 'publishedRecommendations'
     */
    public $expand = [];

    /**
     * @type integer
     */
    public $offset = 0;

    /**
     * @type integer
     */
    public $limit = 50;

    /** PRIVATE */

    /**
     * @type User                   Пользователь, который запрашивает посты
     */
    private $_user;

    /**
     * @inheritdoc
     */
    public function formName()
    {
        return '';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ["userId", "required"],

            [["userId", "searchString", "postCategoryIds", "language"], "trim"],
            ["userId", "integer"],

            ["userId", "exist", "targetClass" => User::class, "targetAttribute" => "id"],

            ["postCategoryIds", "filter", "filter" => function ($value) {
                return empty($value) ? [] : explode(",", $value);
            }],
            ['postCategoryIds', 'each', 'rule' => ['trim']],
            ["postCategoryIds",'each', 'rule' => ["exist", "targetClass" => PostCategory::class, "targetAttribute" => "id"]],

            ["searchString", "string", "max" => 255],
            ["language", "string", "max" => 2],

            ["favoriteOnly", 'boolean'],

            ["expand", "filter", "filter" => function ($value) {
                return empty($value) ? [] : explode(",", $value);
            }],
            ['expand', 'each', 'rule' => ['trim']],

            [["offset", "limit"], "integer"]
        ];
    }

    /**
     * @inheritDoc
     */
    public function validate($attributeNames = null, $clearErrors = true)
    {
        $valid = parent::validate($attributeNames, $clearErrors);

        if ($valid) {
            if (!in_array($this->language, \Yii::$app->localeTool->implementedLanguages)) {
                $this->language = \Yii::$app->user->identity->language;
            }
            if ($this->searchString) {
                $this->partsOfSearch = explode(" ", $this->searchString);
                $this->searchString = null;
            }
        }

        return $valid;
    }

    /**
     * @return string        Term by type and age of users babies
     */
    private function getBabiesTerms()
    {
        $alias = Post::tableName();

        $diaryTypeAll = Post::DIARY_TYPE_ALL;
        $diaryTypeBirth = Post::DIARY_TYPE_BABY;
        $diaryTypePregnancy = Post::DIARY_TYPE_PREGNANCY;

        $terms = "{$alias}.diary_type = '{$diaryTypeAll}'";

        foreach ($this->user->babies as $baby) {
            $age = $baby->approximateAge;
            if ($baby->isPregnancyDiary()) {
                $pregnancyTerms = " OR (
                {$alias}.diary_type = '{$diaryTypePregnancy}'
                AND 
                ({$alias}.age_from <= {$age} OR {$alias}.age_from IS NULL) 
                AND 
                ({$alias}.age_to > {$age} OR {$alias}.age_to IS NULL)
                )";

                $terms .= $pregnancyTerms;
            } else {
                $birthTerms = " OR (
                {$alias}.diary_type = '{$diaryTypeBirth}'
                AND 
                ({$alias}.age_from <= {$age} OR {$alias}.age_from IS NULL) 
                AND 
                ({$alias}.age_to > {$age} OR {$alias}.age_to IS NULL)
                )";

                $terms .= $birthTerms;
            }
        }

        return $terms;
    }

    /**
     * @return PostQuery
     */
    public function query()
    {
        $lastViewedPostId = PostView::lastViewedPostId($this->userId);
        if (empty($lastViewedPostId)) {
            $lastViewedPostId = 0;
        }

        $postAlias = Post::tableName();

        $query = PostViewForm::find()
            ->select("*")
            ->onlyActual()
            ->andWhere($this->getBabiesTerms());

        $query->addSelect("(post.id > {$lastViewedPostId}) AS novelty");

        if ($this->language) {
            $query->byLanguage($this->language);
        }

        $query->orWhere(["{$postAlias}.user_id" => $this->userId, "{$postAlias}.deleted" => null]);

        if (!empty($this->partsOfSearch)) {
            $query->innerJoinWith('user', true);
            $authorAlias = User::tableName();
            foreach ($this->partsOfSearch as $part) {
                $query->andWhere(['or', ["like", "{$postAlias}.text", $part], ["like", "{$postAlias}.title", $part], ["like", "{$authorAlias}.name", $part]]);
            }
        }

        if ($this->favoriteOnly) {
            $query->andWhere("EXISTS(SELECT 1 FROM favorite f WHERE f.post_id = post.id AND f.user_id = {$this->userId})");
        }

        if ($this->postCategoryIds) {
            $query->byCategoriesId($this->postCategoryIds);
        }

        $queryForTotal = clone($query);
        \Yii::$app->response->headers->add('X-Total-Count', $queryForTotal->orderBy([])->count());
        \Yii::$app->response->headers->add('X-Records-Limit', $this->limit);
        \Yii::$app->response->headers->add('X-Records-Offset', $this->offset);

        if ($this->offset) {
            $query->offset($this->offset);
        }

        if ($this->limit) {
            $query->limit($this->limit);
        }

        $query->orderBy(["{$postAlias}.created" => SORT_DESC]);

        return $query;
    }

    /**
     * @return PostViewForm[]
     */
    public function getModels()
    {
        $extraFields = $this->expand;

        return array_map(
            function (PostViewForm $model) use ($extraFields) {
                return $model->toArray([], $extraFields, true);
            },
            $this->query()->all()
        );
    }

    /**
     * @return bool|int|string|null
     */
    public function totalNoveltiesCount()
    {
        $this->favoriteOnly = false;
        $this->expand = [];
        $this->offset = null;
        $this->limit = null;
        $this->postCategoryIds = null;
        $this->partsOfSearch = null;

        $lastViewedPostId = PostView::lastViewedPostId($this->userId);
        if (empty($lastViewedPostId)) {
            $lastViewedPostId = 0;
        }

        return $this->query()->andWhere([">", "post.id", $lastViewedPostId])->count();
    }

    /**
     * @return User|null
     */
    public function getUser()
    {
        if ($this->_user) {
            return $this->_user;
        }

        $this->_user = User::findOne($this->userId);

        return $this->_user;
    }
}