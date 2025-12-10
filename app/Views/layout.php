<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>PHP MVC App</title>
    <link rel="stylesheet" href="/public/css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1><a href="/?route=home/index">PHP MVC App</a></h1>
        </header>

        <?php if ($msg = flash('success')): ?>
            <div class="flash success"><?php echo htmlspecialchars($msg); ?></div>
        <?php endif; ?>
        <?php if ($msg = flash('error')): ?>
            <div class="flash error"><?php echo htmlspecialchars($msg); ?></div>
        <?php endif; ?>

        <main>
            <?php echo $content; ?>
        </main>

        <footer>
            <p>Simple MVC structure created for somethingpossible/php-project</p>
        </footer>
    </div>
</body>
</html>
