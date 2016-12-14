<?php
/*******************************************************************************
 * rodzeta.redirect - SEO redirects module
 * Copyright 2016 Semenov Roman
 * MIT License
 ******************************************************************************/

namespace Rodzeta\Redirect;

defined("B_PROLOG_INCLUDED") and (B_PROLOG_INCLUDED === true) or die();

use Bitrix\Main\{Loader, EventManager, Config\Option};

require __DIR__ . "/.init.php";

EventManager::getInstance()->addEventHandler("main", "OnPanelCreate", function () {
	global $USER, $APPLICATION;
	// TODO заменить на определение доступа к редактированию конента
	if (!$USER->IsAdmin()) {
	  return;
	}

	$link = "javascript:" . $APPLICATION->GetPopupLink([
		"URL" => URL_ADMIN,
		"PARAMS" => [
			"resizable" => true,
			//"width" => 780,
			//"height" => 570,
			//"min_width" => 400,
			//"min_height" => 200,
			"buttons" => "[BX.CDialog.prototype.btnClose]"
		]
	]);
  $APPLICATION->AddPanelButton([
		"HREF" => $link,
		"ICON"  => "bx-panel-site-structure-icon",
		//"SRC" => URL_ADMIN . "/icon.gif",
		"TEXT"  => "Типовые редиректы",
		"ALT" => "Типовые редиректы",
		"MAIN_SORT" => 2000,
		"SORT"      => 200
	]);

	$link = "javascript:" . $APPLICATION->GetPopupLink([
		"URL" => URL_ADMIN . "/urls/",
		"PARAMS" => [
			"resizable" => true,
			//"width" => 780,
			//"height" => 570,
			//"min_width" => 400,
			//"min_height" => 200,
			"buttons" => "[BX.CDialog.prototype.btnClose]"
		]
	]);
  $APPLICATION->AddPanelButton([
		"HREF" => $link,
		"ICON"  => "bx-panel-site-structure-icon",
		//"SRC" => URL_ADMIN . "/icon.gif",
		"TEXT"  => "Список редиректов",
		"ALT" => "Список редиректов",
		"MAIN_SORT" => 2000,
		"SORT"      => 220
	]);
});

EventManager::getInstance()->addEventHandler("main", "OnBeforeProlog", function () {
	if (\CSite::InDir("/bitrix/") ||
				($_SERVER["REQUEST_METHOD"] != "GET" && $_SERVER["REQUEST_METHOD"] != "HEAD")) {
		return;
	}

	$host = $_SERVER["SERVER_NAME"];
	$protocol = !empty($_SERVER["HTTPS"])
		&& $_SERVER["HTTPS"] != "off"? "https" : "http";
	$port = !empty($_SERVER["SERVER_PORT"])
		&& $_SERVER["SERVER_PORT"] != "80"
		&& $_SERVER["SERVER_PORT"] != "443"?
			(":" . $_SERVER["SERVER_PORT"]) : "";
	$url = null;
	$isAbsoluteUrl = false;

	if (Option::get("rodzeta.redirect", "redirect_www") == "Y" && substr($_SERVER["SERVER_NAME"], 0, 4) == "www.") {
		$host = substr($_SERVER["SERVER_NAME"], 4);
		$url = $_SERVER["REQUEST_URI"];
	}

	$toProtocol = Option::get("rodzeta.redirect", "redirect_https");
	if ($toProtocol == "to_https" && $protocol == "http") {
		$protocol = "https";
		$url = $_SERVER["REQUEST_URI"];
	} else if ($toProtocol == "to_http" && $protocol == "https") {
		$protocol = "http";
		$url = $_SERVER["REQUEST_URI"];
	}

	if (Option::get("rodzeta.redirect", "redirect_index") == "Y"
				|| Option::get("rodzeta.redirect", "redirect_slash") == "Y"
				|| Option::get("rodzeta.redirect", "redirect_multislash") == "Y") {
		$changed = false;
		$u = parse_url($_SERVER["REQUEST_URI"]);
		if (Option::get("rodzeta.redirect", "redirect_index") == "Y") {
			$tmp = rtrim($u["path"], "/");
			if (basename($tmp) == "index.php") {
				$dname = dirname($tmp);
				$u["path"] = ($dname != DIRECTORY_SEPARATOR? $dname : "") . "/";
				$changed = true;
			}
		}
		if (Option::get("rodzeta.redirect", "redirect_slash") == "Y") {
			// add slash to url
			if (substr($u["path"], -1, 1) != "/"
						&& substr(basename(rtrim($u["path"], "/")), -4) != ".php") {
				$u["path"] .= "/";
				$changed = true;
			}
		}
		if (Option::get("rodzeta.redirect", "redirect_multislash") == "Y") {
			if (strpos($u["path"], "//") !== false) {
				$u["path"] = preg_replace('{/+}s', "/", $u["path"]);
				$changed = true;
			}
		}
		if ($changed) {
			$url = $u["path"];
			if (!empty($u["query"])) {
				$url .= "?" . $u["query"];
			}
		}
	}

	$redirects = Utils::getMap();
	if (isset($redirects[$_SERVER["REQUEST_URI"]])) {
		$url = $redirects[$_SERVER["REQUEST_URI"]];
		if (substr($url, 0, 4) == "http") {
			$isAbsoluteUrl = true;
		}
	}

	if (!empty($url)) {
		if ($isAbsoluteUrl) {
			LocalRedirect($url, true, "301 Moved Permanently");
		} else {
			LocalRedirect($protocol . "://" . $host . $port . $url, true, "301 Moved Permanently");
		}
		exit;
	}
});
