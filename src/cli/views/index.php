<!DOCTYPE html>
<html lang="en">

<head>
  <title>Amqp Status</title>
  <link href="css/supervisor.css" rel="stylesheet" type="text/css">
  <link href="images/icon.png" rel="icon" type="image/png">
</head>


<body>
  <div id="wrapper">

    <div id="header">
      <img alt="Supervisor status" src="images/supervisor.gif">
    </div>

    <div>
      <div class="status_msg">All restarted at Sat Dec 26 14:26:02 2020</div>

      <ul class="clr" id="buttons">
        <li class="action-button"><a href="index.html?action=refresh">Refresh</a></li>
        <li class="action-button"><a href="index.html?action=restartall">Restart All</a></li>
        <li class="action-button"><a href="index.html?action=stopall">Stop All</a></li>
      </ul>

      <table cellspacing="0">
        <thead>
          <tr>
            <th class="state">State</th>
            <th class="desc">Description</th>
            <th class="name">Name</th>
            <th class="action">Action</th>
          </tr>
        </thead>

        <tbody>
          <?php

          use pzr\amqp\cli\Consumer;

          if ($this->consumers) foreach ($this->consumers as $c) { ?>
            <tr class="">
              <td class="status"><span class="status<?= $c->state ?>"><?= $c->state ?></span></td>
              <td><span>pid <?= $c->pid ?>, <?= $c->uniqid ?></span></td>
              <td><a href="tail.html?uniqid=<?= $c->uniqid ?>" target="_blank"><?= $c->queue ?></a></td>
              <td class="action">
                <ul>



                  <?php if ($c->state == Consumer::RUNNING) { ?>
                    <li>
                      <a href="index.html?uniqid=<?= $c->uniqid ?>&action=restart" name="Restart">Restart</a>
                    </li>
                    <li>
                      <a href="index.html?uniqid=<?= $c->uniqid ?>&action=stop" name="Stop">Stop</a>
                    </li>
                  <?php } else { ?>
                    <li>
                      <a href="index.html?uniqid=<?= $c->uniqid ?>&action=start" name="Stop">Start</a>
                    </li>
                  <?php } ?>
                  <li>
                    <a href="index.html?uniqid=<?= $c->uniqid ?>&action=copy" name="Copy">Copy</a>
                  </li>
                  <li>
                    <a href="stderr.php?uniqid=<?= $c->uniqid ?>" name="Tail -f Stderr" target="_blank">Tail -f Stderr</a>
                  </li>
                </ul>
              </td>
            </tr>
          <?php  } ?>
        </tbody>
      </table>

    </div>
</body>

</html>