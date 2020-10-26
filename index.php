<?php
/*
 * GG plugins
 * https://qiita.com/TanakanoAnchan/items/f1191c4c008f0a2b7c2e
 * https://oxynotes.com/?p=9321#3
 *
  Plugin Name: ggProtectedContents
  Plugin URI:
  Description: 指定コンテンツにWPユーザー権限に応じて閲覧制限をかける
  Version: 1.0.0
  Author: Yamadante
  Author URI:
  License: GPLv2
 */

if (!class_exists('ggProtectedContents')) {

    /*
     *
     * 一部コンテンツに閲覧制限をかける
     * */

    class ggProtectedContents
    {
        /*
         * プロパティ
         * */

        /**
         * ログイン時のURL
         * @var string
         */
        public $login_url = '/login/';

        /**
         * 閲覧対象のユーザー権限
         * @var array
         */
        public $user_role = [
            'subscriber' => 'subscriber',
            'administrator' => 'administrator',
            'editor' => 'editor'
        ];


        /**
         * 制限をかける投稿タイプ
         * @var array
         */
        public $types = [
            'post',
        ];

        /**
         * 制限をかけるカテゴリー
         * @var array
         */
        public $cats = [
            'category'
        ];

        /**
         * 制限をかけるtag
         * @var array
         */
        public $tags = [
            'tag'
        ];

        /**
         * 制限をかけるカスタム投稿タイプ
         * @var array
         */
        public $custom_types = [
        ];

        /**
         * 制限をかけるカスタムタクソノミー
         * @var array
         */
        public $custom_tax = [
        ];

        /**
         * 固定ページの場合の制限をかけるURL
         * @var array
         */
        public $urls = [
            '/test/',
        ];

        /**
         * コンストラクタ
         * ggProtectedContents constructor.
         */
        public function __construct()
        {
            //※add_filterの場合は戻り値が帰ってくるのでそれを操作。add_actionは関数内で処理を完結する。
            add_action('init', array($this, 'isCheckLoggedIn'));
            add_action('init', array($this, 'isCheckCapsSubscriber'));
            add_action('init', array($this, 'isCheckCapsOther'));
            add_action('wp_login', array($this, 'isCheckCapsRedirect'), 10);
            add_action('wp_logout', array($this, 'logoutRedirect'), 10, 2);
            add_action('auth_redirect', array($this, 'notEnterManagement'), 10);
            add_action('after_setup_theme', array($this, 'notShowAdminBar'), 10);
            add_action('pre_get_posts', array($this, 'canViewContents'), 10);
            add_action('pre_get_posts', array($this, 'checkView'), 10, 1);

        }

        /**
         * ログインフォームを出力する
         */
        public function displayLoginForm()
        {
            echo wp_login_form();
        }

        /**
         * ログアウト用のリンクを出力する
         */
        public function display()
        {
            echo '<a href="' . wp_logout_url(site_url($_SERVER["REQUEST_URI"])) . '">ログアウト</a>';
        }


        /**
         * ログインしているか判定する
         * @return bool
         */
        public function isCheckLoggedIn()
        {
            if (is_user_logged_in()) {
                return true;
            }
            return false;
        }

        /**
         * ログインしているユーザーが購読者ユーザーか判定する
         * @return bool
         */
        public function isCheckCapsSubscriber()
        {
            if ($this->isCheckLoggedIn()) {
                $current_user = wp_get_current_user();
                if ($current_user) {
                    $_userdata = $current_user->data;
                    $userdata = get_userdata($_userdata->ID);
                    if ($userdata) {
                        if ($this->user_role['subscriber'] === $userdata->roles[0]) {
                            return true;
                        }
                    }
                }
            }

            return false;
        }

        /**
         * ログインしているユーザーが購読者ユーザー以外で許可する権限か判定する
         * @return bool
         */
        public function isCheckCapsOther()
        {
            if ($this->isCheckLoggedIn()) {
                $current_user = wp_get_current_user();
                if ($current_user) {
                    $_userdata = $current_user->data;
                    $userdata = get_userdata($_userdata->ID);
                    if ($userdata) {
                        if ($this->user_role['administrator'] === $userdata->roles[0] || $this->user_role['editor'] === $userdata->roles[0]) {
                            return true;
                        }
                    }
                }
            }
            return false;
        }


        /**
         * ログイン時、ユーザー権限を判定して、管理画面ではなくページへ移動させる
         */
        public function isCheckCapsRedirect()
        {
            if ($this->isCheckCapsSubscriber()) {
                wp_safe_redirect($this->login_url);
                exit();
            }
        }

        /**
         * ログアウト時、ユーザー権限を判定して、管理画面ではなくページへ移動させる
         */
        public function logoutRedirect()
        {
            if ($this->isCheckCapsSubscriber()) {
                wp_safe_redirect($this->login_url);
                exit();
            }
        }

        /**
         * ユーザー権限を判定して、対象ユーザー権限はWordPressの管理画面へは入場させない
         */
        public function notEnterManagement()
        {
            $redirects = false;
            if ($this->isCheckCapsSubscriber()) {
                $redirects = true;
            }

            if ($redirects) {
                wp_safe_redirect($this->login_url);
                exit();
            }

        }

        /**
         * ユーザー権限を判定して、対象ユーザー権限は管理バーを表示させない
         */
        public function notShowAdminBar()
        {
            if ($this->isCheckCapsSubscriber()) {
                show_admin_bar(false);
            }
        }

        /**
         * 現在のURLが対象の投稿タイプ、もしくはURLか判定する
         * @return bool
         */
        public function canViewContents()
        {
            //投稿
            if (is_singular(is_post_type_archive($this->types)) || is_post_type_archive($this->types) || is_tax($this->custom_tax) || is_category($this->cats) || is_category($this->tags)) {
                return true;
            }

            //固定ページ
            if (is_page()) {
                $_url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH); //クエリがあった場合、除去
                $target_urls = $this->urls;
                $_searched = array_search($_url, $target_urls);
                if ($_searched === 0 || $_searched !== false) {
                    return true;
                }
            }

            return false;
        }


        /**
         * 対象の投稿タイプ/URLだった場合、ログインしているか/権限は有効化かを判定して、
         * 閲覧させるべきでない場合、リダイレクトする
         * @return bool
         */
        public function checkView()
        {
            $checked = false;

            //ログインユーザーは購読者か
            if ( $this->isCheckLoggedIn() ) {
                $checked = true;
            } else {
                //対象ページ、もしくは対象投稿タイプか
                if ($this->canViewContents()) {
                    $checked = false;
                }
            }


            //チェックがfalseの場合の通過項目
            if (!$checked) {


                if (is_admin() || is_preview()) {
                    return true;
                }

                if ($_SERVER['REQUEST_URI'] === $this->login_url) {
                    return true;
                }

                wp_safe_redirect(home_url() . $this->login_url, 302);
                exit();
            }
        }

    }

    $ggProtectedContents = new ggProtectedContents();
}

