<?php

namespace api\versions\v4_3\controllers;

use common\models\babydayka\posts\PostView;
use yii\filters\AccessControl;
use yii\filters\auth\CompositeAuth;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use api\components\UserTokenAuth;

use api\versions\v4_3\models\posts\PostReadingLog;
use api\versions\v4_3\models\documents\Document;
use api\versions\v4_3\models\posts\forms\PLikeToggleForm;
use api\versions\v4_3\models\posts\PostCategory;
use api\versions\v4_3\models\posts\forms\PostEditForm;
use api\versions\v4_3\models\posts\forms\PostsSearchForm;
use api\versions\v4_3\models\posts\forms\PostViewForm;
use api\versions\v4_3\models\posts\Post;
use api\versions\v4_3\components\Controller;
use api\versions\v4_3\models\user\User;

/**
 * Class PostsController
 */
class PostsController extends Controller
{
    /**
     * @inheritDoc
     */
    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'authenticator' => [
                'class' => CompositeAuth::class,
                'authMethods' => [
                    "class" => UserTokenAuth::class
                ]
            ],
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'actions' => ['edit', 'create', "toggle-like", "delete", 'toggle-favorite', 'mark-viewed', "mark-unviewed"],
                        'roles' => ['@'],
                        "verbs" => ["POST"]
                    ],
                    [
                        'allow' => true,
                        'actions' => ['index', "view", "get-post-categories", "get-chat-rules", 'favorites', 'novelties-count'],
                        'roles' => ['@'],
                        "verbs" => ["GET"]
                    ],
                ],
            ],
        ]);
    }

    /**
     * Вернет массив постов по параметрам запроса
     *
     * @return array|Post[]
     *
     * @see PostsSearchForm
     */
    public function actionIndex()
    {
        /** @var User $user */
        $user = \Yii::$app->user->identity;

        $search = new PostsSearchForm(["userId" => $user->id]);

        if ($search->load(\Yii::$app->request->get())) {
            if ($search->validate()) {
                return $search->getModels();
            } else {
                return $this->prepareFailureResponse($search->errors);
            }
        }

        return [];
    }

    /**
     * Пометит все посты как прочтенные
     *
     * @return array|bool
     */
    public function actionMarkViewed()
    {
        /** @var User $user */
        $user = \Yii::$app->user->identity;

        PostView::markViewed($user->id);

        return true;
    }

    /**
     * Пометит все посты как прочтенные
     *
     * @return array|bool
     */
    public function actionMarkUnviewed()
    {
        /** @var User $user */
        $user = \Yii::$app->user->identity;

        $postView = PostView::find()->andWhere(["user_id" => $user->id])->one();

        return boolval($postView->delete());
    }

    /**
     * Вернет кол-во не прочтенных постов
     *
     * @return integer
     */
    public function actionNoveltiesCount()
    {
        /** @var User $user */
        $user = \Yii::$app->user->identity;

        $search = new PostsSearchForm(["userId" => $user->id]);

        if ($search->load(\Yii::$app->request->get())) {
            if ($search->validate()) {
                return $search->totalNoveltiesCount();
            } else {
                return $this->prepareFailureResponse($search->errors);
            }
        }

        return 0;
    }

    /**
     * Избранные темы
     *
     * @return \common\models\babydayka\posts\Post[]
     */
    public function actionFavorites()
    {
        $user = \common\models\babydayka\user\User::findOne(\Yii::$app->user->identity->id);
        return $user->favoritePosts;
    }

    /**
     * поставить/убрать отметку "избранное" для темы с идентификатором POST['post_id']
     *
     * @return bool
     * @throws \yii\base\InvalidConfigException
     */
    public function actionToggleFavorite()
    {
        $user = \common\models\babydayka\user\User::findOne(\Yii::$app->user->identity->id);
        return $user->toggleFavoritePost(\Yii::$app->request->post('post_id'));
    }

    /**
     * @return array|mixed|PostEditForm
     */
    public function actionCreate()
    {
        /** @var User $user */
        $user = \Yii::$app->user->identity;

        if ($user->isBannedInBabychat()) {
            throw new ForbiddenHttpException(\Yii::t('babychat', "Пользователь заблокирован"));
        }

        $postCreateForm = new PostEditForm(["user_id" => $user->id]);

        if (!$postCreateForm->load(\Yii::$app->request->post()) || !$postCreateForm->validate()) {
            return $this->prepareFailureResponse($postCreateForm->errors);
        }

        $postCreateForm->apply();
        $postCreateForm->refresh();

        \common\models\babydayka\posts\Post::findOne($postCreateForm->id)->makeFavorite();

        return $postCreateForm;
    }

    /**
     * @return array|mixed|PostEditForm
     */
    public function actionEdit()
    {
        $id = intval(\Yii::$app->request->post("id"));
        $postEditForm = PostEditForm::findOne($id);
        if (empty($postEditForm)) {
            throw new NotFoundHttpException("Post with id = {$id} not found");
        }

        /** @var User $user */
        $user = \Yii::$app->user->identity;
        if ($user->isBannedInBabychat()) {
            throw new ForbiddenHttpException(\Yii::t('babychat', "Пользователь заблокирован"));
        }
        if ($user->id !== $postEditForm->user_id && !$user->isBabyChatAdmin()) {
            throw new ForbiddenHttpException("Permission denied");
        }

        if (!$postEditForm->load(\Yii::$app->request->post()) || !$postEditForm->validate()) {
            return $this->prepareFailureResponse($postEditForm->errors);
        }

        $postEditForm->apply();
        $postEditForm->refresh();

        return $postEditForm;
    }

    /**
     * @param integer $id
     * @return array|mixed|PostViewForm
     */
    public function actionView($id)
    {
        $post = PostViewForm::findOne($id);

        $post->supplicantUser = \Yii::$app->user->identity;

        if (empty($post)) {
            throw new NotFoundHttpException("Post with id = {$id} does not exist");
        }


        (new PostReadingLog([
            "user_id" => \Yii::$app->user->identity->id,
            "post_id" => $post->id,
        ]))->save();

        return $post;
    }

    /**
     * @return array|mixed|PostEditForm
     */
    public function actionDelete()
    {
        $id = intval(\Yii::$app->request->post("id"));
        $postEditForm = PostEditForm::findOne($id);
        if (empty($postEditForm)) {
            throw new NotFoundHttpException("Post with id = {$id} not found");
        }

        /** @var User $user */
        $user = \Yii::$app->user->identity;
        if ($user->isBannedInBabychat()) {
            throw new ForbiddenHttpException(\Yii::t('babychat', "Пользователь заблокирован"));
        }
        if ($user->id !== $postEditForm->user_id && !$user->isBabyChatAdmin()) {
            throw new ForbiddenHttpException("Permission denied");
        }

        return $postEditForm->delete();
    }

    /**
     * @return PLikeToggleForm|array|mixed
     */
    public function actionToggleLike()
    {
        /** @var User $user */
        $user = \Yii::$app->user->identity;
        $likeToggleForm = new PLikeToggleForm(["userId" => $user->id]);

        if (!$likeToggleForm->load(\Yii::$app->request->post()) || !$likeToggleForm->validate()) {
            return $this->prepareFailureResponse($likeToggleForm->errors);
        }

        $isLiked = $likeToggleForm->toggle();

        if ($isLiked) {
            \common\models\babydayka\posts\Post::findOne($likeToggleForm->postId)->makeFavorite();
        }

        return ["isLiked" => $isLiked];
    }

    /**
     * @return PostCategory[]
     */
    public function actionGetPostCategories()
    {
        return PostCategory::find()->all();
    }

    /**
     * @param string $language
     * @return Document
     */
    public function actionGetChatRules($language)
    {
        $language = $language ?: \Yii::$app->user->identity->language;

        return Document::find()->chatRules()->byLanguage($language)->lastVersion();
    }
}