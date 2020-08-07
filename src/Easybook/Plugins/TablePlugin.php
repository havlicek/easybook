<?php declare(strict_types=1);

/*
 * This file is part of the easybook application.
 *
 * (c) Javier Eguiluz <javier.eguiluz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Easybook\Plugins;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Easybook\Events\EasybookEvents as Events;
use Easybook\Events\ParseEvent;

/**
 * It performs some operations on the book tables, such as
 * decorating their contents and adding labels to them.
 */
class TablePlugin implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            Events::POST_PARSE => ['decorateAndLabelTables', -500],
        ];
    }

    /**
     * It decorates each table with a template and, if the edition configures it,
     * with the appropriate auto-numbered label.
     *
     * @param ParseEvent $event The object that contains the item being processed
     */
    public function decorateAndLabelTables(ParseEvent $event): void
    {
        $item = $event->getItem();

        $addTableLabels = in_array('table', $event->app->edition('labels') ?: [], true);
        $parentItemNumber = $item['config']['number'];
        $listOfTables = [];
        $counter = 0;

        $item['content'] = preg_replace_callback(
            "/(?<content><table.*\n<\/table>)/Ums",
            static function ($matches) use ($event, $addTableLabels, $parentItemNumber, &$listOfTables, &$counter) {
                // prepare table parameters for template and label
                $counter++;
                $parameters = [
                    'item' => [
                        'caption' => '',
                        'content' => $matches['content'],
                        'label' => '',
                        'number' => $counter,
                        'slug' => $event->app->slugify('Table '.$parentItemNumber.'-'.$counter),
                    ],
                    'element' => [
                        'number' => $parentItemNumber,
                    ],
                ];

                // the publishing edition wants to label tables
                if ($addTableLabels) {
                    $label = $event->app->getLabel('table', $parameters);
                    $parameters['item']['label'] = $label;
                }

                // add table details to the list-of-tables
                $listOfTables[] = $parameters;

                return $event->app->render('table.twig', $parameters);
            },
            $item['content']
        );

        if (count($listOfTables) > 0) {
            $event->app->append('publishing.list.tables', $listOfTables);
        }

        $event->setItem($item);
    }
}
