<?php

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\InstallationBundle\Database;

use Contao\StringUtil;

/**
 * Runs the version 4.0.0 update.
 *
 * @author Leo Feyer <https://github.com/leofeyer>
 */
class Version400Update extends AbstractVersionUpdate
{
    /**
     * {@inheritdoc}
     */
    public function shouldBeRun()
    {
        $schemaManager = $this->connection->getSchemaManager();

        if (!$schemaManager->tablesExist(['tl_layout'])) {
            return false;
        }

        $columns = $schemaManager->listTableColumns('tl_layout');

        return !isset($columns['scripts']);
    }

    /**
     * {@inheritdoc}
     */
    public function run()
    {
        $this->connection->query('
            ALTER TABLE
                tl_layout
            ADD
                scripts text NULL
        ');

        // Adjust the framework agnostic scripts
        $statement = $this->connection->query("
            SELECT
                id, addJQuery, jquery, addMooTools, mootools
            FROM
                tl_layout
            WHERE
                framework != ''
        ");

        while (false !== ($layout = $statement->fetch(\PDO::FETCH_OBJ))) {
            $scripts = [];

            // Check if j_slider is enabled
            if ($layout->addJQuery) {
                $jquery = StringUtil::deserialize($layout->jquery);

                if (!empty($jquery) && \is_array($jquery)) {
                    $key = array_search('j_slider', $jquery, true);

                    if (false !== $key) {
                        $scripts[] = 'js_slider';
                        unset($jquery[$key]);

                        $stmt = $this->connection->prepare('
                            UPDATE
                                tl_layout
                            SET
                                jquery = :jquery
                            WHERE
                                id = :id
                        ');

                        $stmt->execute([':jquery' => serialize(array_values($jquery)), ':id' => $layout->id]);
                    }
                }
            }

            // Check if moo_slider is enabled
            if ($layout->addMooTools) {
                $mootools = StringUtil::deserialize($layout->mootools);

                if (!empty($mootools) && \is_array($mootools)) {
                    $key = array_search('moo_slider', $mootools, true);

                    if (false !== $key) {
                        $scripts[] = 'js_slider';
                        unset($mootools[$key]);

                        $stmt = $this->connection->prepare('
                            UPDATE
                                tl_layout
                            SET
                                mootools = :mootools
                            WHERE
                                id = :id
                        ');

                        $stmt->execute([':mootools' => serialize(array_values($mootools)), ':id' => $layout->id]);
                    }
                }
            }

            // Enable the js_slider template
            if (!empty($scripts)) {
                $stmt = $this->connection->prepare('
                    UPDATE
                        tl_layout
                    SET
                        scripts = :scripts
                    WHERE
                        id = :id
                ');

                $stmt->execute([':scripts' => serialize(array_values($scripts)), ':id' => $layout->id]);
            }
        }

        // Replace moo_slimbox with moo_mediabox
        $statement = $this->connection->query("
            SELECT
                id, mootools
            FROM
                tl_layout
            WHERE
                framework != ''
        ");

        while (false !== ($layout = $statement->fetch(\PDO::FETCH_OBJ))) {
            /** @var array $mootools */
            $mootools = StringUtil::deserialize($layout->mootools);

            if (!empty($mootools) && \is_array($mootools)) {
                $key = array_search('moo_slimbox', $mootools, true);

                if (false !== $key) {
                    $mootools[] = 'moo_mediabox';
                    unset($mootools[$key]);

                    $stmt = $this->connection->prepare('
                        UPDATE
                            tl_layout
                        SET
                            mootools = :mootools
                        WHERE
                            id = :id
                    ');

                    $stmt->execute([':mootools' => serialize(array_values($mootools)), ':id' => $layout->id]);
                }
            }
        }

        // Adjust the list of framework style sheets
        $statement = $this->connection->query("
            SELECT
                id, framework
            FROM
                tl_layout
            WHERE
                framework != ''
        ");

        while (false !== ($layout = $statement->fetch(\PDO::FETCH_OBJ))) {
            $framework = StringUtil::deserialize($layout->framework);

            if (!empty($framework) && \is_array($framework)) {
                $key = array_search('tinymce.css', $framework, true);

                if (false !== $key) {
                    unset($framework[$key]);

                    $stmt = $this->connection->prepare('
                        UPDATE
                            tl_layout
                        SET
                            framework = :framework
                        WHERE
                            id = :id
                    ');

                    $stmt->execute([':framework' => serialize(array_values($framework)), ':id' => $layout->id]);
                }
            }
        }

        // Adjust the module types
        $this->connection->query("
            UPDATE
                tl_module
            SET
                type = 'articlelist'
            WHERE
                type = 'articleList'
        ");

        $this->connection->query("
            UPDATE
                tl_module
            SET
                type = 'rssReader'
            WHERE
                type = 'rss_reader'
        ");

        $this->connection->query("
            UPDATE
                tl_form_field
            SET
                type = 'explanation'
            WHERE
                type = 'headline'
        ");
    }

    private function checkCustomTemplates()
    {
        static $mapper = [
            'tl_article' => [
                'mod_article_plain' => 'mod_article',
                'mod_article_teaser' => 'mod_article',
            ],
            'tl_content' => [
                'ce_hyperlink_image' => 'ce_hyperlink',
            ],
            'tl_module' => [
                'mod_login_1cl' => 'mod_login',
                'mod_login_2cl' => 'mod_login',
                'mod_logout_1cl' => 'mod_login',
                'mod_logout_2cl' => 'mod_login',
                'mod_search_advanced' => 'mod_search',
                'mod_search_simple' => 'mod_search',
                'mod_eventmenu_year' => 'mod_eventmenu',
                'mod_newsmenu_day' => 'mod_newsmenu',
                'mod_newsmenu_year' => 'mod_newsmenu',
            ],
        ];

        foreach ($mapper as $table => $templates) {
            $stmt = $this->connection->prepare("
                SELECT
                    *
                FROM
                    $table
                WHERE
                    customTpl = :template
            ");

            foreach ($templates as $old => $new) {
                $stmt->bindValue(':template', $old);
                $stmt->execute();

                if (false !== ($row = $stmt->fetch(\PDO::FETCH_OBJ))) {
                    $this->addMessage(sprintf('<li>%s.%s → %s</li>', $table, $row->id, $old));
                }
            }
        }

        if ($this->hasMessage()) {
            $translator = $this->container->get('translator');

            $this->prependMessage(sprintf(
                '<h3>%s</h3><p>%s</p><ul>',
                $translator->trans('old_templates'),
                $translator->trans('old_templates_begin')
            ));

            $this->addMessage(sprintf('</ul><p>%s</p>', $translator->trans('old_templates_end')));
        }
    }
}
