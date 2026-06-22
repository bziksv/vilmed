<?php
session_start();

function vilmedCaptchaSafeRedirect($url)
{
	$url = trim((string)$url);
	if ($url === "" || strlen($url) > 2000) {
		return "/";
	}
	if ($url[0] === "/" && ($url[1] ?? "") !== "/") {
		return $url;
	}
	$parts = parse_url($url);
	if (empty($parts["host"])) {
		return "/";
	}
	$reqHost = isset($_SERVER["HTTP_HOST"]) ? strtolower(preg_replace("~:\d+$~", "", (string)$_SERVER["HTTP_HOST"])) : "";
	$urlHost = strtolower(preg_replace("~:\d+$~", "", (string)$parts["host"]));
	if ($reqHost !== "" && $urlHost === $reqHost) {
		return $url;
	}
	return "/";
}

$_SESSION['captcha'] = rand(1, 10);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['captcha'])) {
	$captcha = (int) $_POST['captcha'];
	$page = vilmedCaptchaSafeRedirect($_POST['page'] ?? '/');

	if ($captcha === $_SESSION['captcha']) {
		header('Location: ' . $page);
		exit;
	}
	header('Location: captcha.php?error=Извините вы ошиблись, попробуйте снова');
	exit;
}


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Простая капча</title>
  
 <style>
  /* Стили для адаптивной верстки */
  * {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
  }
  body {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
    height: 100vh;
    background-color: #f2f2f2;
  }
  form {
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 100%;
    max-width: 100%;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 5px;
    background-color: #fff;
    box-shadow: 0px 0px 5px 1px rgba(0, 0, 0, 0.2);
    margin-top: 250px;
  }
  input[type="submit"] {
    margin-top: 10px;
    padding: 10px;
    background-color: #007bff;
    color: #fff;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    transition: background-color 0.2s ease-in-out;
  }
  input[type="submit"]:hover {
    background-color: #0062cc;
  }
  input[type="number"] {
    margin-top: 5px;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 1.5rem;
    color: #333;
  }
  label {
    font-size: 1.5rem;
    font-weight: bold;
    color: #333;
  }
  .error-message {
    color: red;
    margin-top: 10px;
    font-size: 1.5rem;
  }
  p {
    text-align: center;
    font-size: 1.5rem;
  }
</style>

</head>
<body>
    <form action="" method="POST">
            <p>Внимание с вашего IP поступает много запросов. Подтвердите, что Вы человек.</p><br>
        <label for="captcha">

        Введите цифру <?php echo $_SESSION['captcha'];?> ниже:</label>
        <input type="number" name="captcha" id="captcha" required>
        <input type="hidden" name="page" value="<?php echo htmlspecialcharsbx($_SERVER['HTTP_REFERER'] ?? '/'); ?>">
        <input type="submit" value="Отправить">
        <?php if (isset($_GET['error'])): ?>
            <div class="error-message"><?php echo $_GET['error']; ?></div>
        <?php endif; ?>
    </form>
</body>
</html>