<?php

/*
 * This file is part of Flarum.
 *
 * (c) Toby Zerner <toby.zerner@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flarum\Discussion\Command;

use Flarum\Discussion\DiscussionRepository;
use Flarum\Discussion\DiscussionValidator;
use Flarum\Discussion\Event\Saving;
use Flarum\Foundation\DispatchEventsTrait;
use Flarum\User\AssertPermissionTrait;
use Illuminate\Contracts\Events\Dispatcher;

class EditDiscussionHandler
{
    use DispatchEventsTrait;
    use AssertPermissionTrait;

    /**
     * @var DiscussionRepository
     */
    protected $discussions;

    /**
     * @var DiscussionValidator
     */
    protected $validator;

    /**
     * @param Dispatcher $events
     * @param DiscussionRepository $discussions
     * @param DiscussionValidator $validator
     */
    public function __construct(Dispatcher $events, DiscussionRepository $discussions, DiscussionValidator $validator)
    {
        $this->events = $events;
        $this->discussions = $discussions;
        $this->validator = $validator;
    }

    /**
     * @param EditDiscussion $command
     * @return \Flarum\Discussion\Discussion
     * @throws \Flarum\User\Exception\PermissionDeniedException
     */
    public function handle(EditDiscussion $command)
    {
        $actor = $command->actor;
        $data = $command->data;
        $attributes = array_get($data, 'attributes', []);

        $discussion = $this->discussions->findOrFail($command->discussionId, $actor);

        if (isset($attributes['title'])) {
            $this->assertCan($actor, 'rename', $discussion);

            $discussion->rename($attributes['title']);
        }

        if (isset($attributes['isHidden'])) {
            $this->assertCan($actor, 'hide', $discussion);

            if ($attributes['isHidden']) {
                $discussion->hide($actor);
            } else {
                $discussion->restore();
            }
        }

        $this->events->fire(
            new Saving($discussion, $actor, $data)
        );

        $this->validator->assertValid($discussion->getDirty());

        $discussion->save();

        $this->dispatchEventsFor($discussion, $actor);

        return $discussion;
    }
}
