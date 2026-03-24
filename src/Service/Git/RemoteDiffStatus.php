<?php

declare(strict_types=1);

namespace HubKit\Service\Git;

enum RemoteDiffStatus: string
{
    case UpToDate = 'up-to-date';

    case NeedPull = 'need_pull';

    case NeedPush = 'need_push';

    case Diverged = 'diverged';
}
