<?php

//共有DBのメンテナンスチェック
if(is_common_db_maintenance()){
    $smarty_object->display("html/common_db_maintenance.tpl");
    return;
}

try {
	// GalaxyPDOインスタンス取得
	$gpdo = GalaxyPDO::instance()->setNames(DB_CHARSET);

	switch ($op) {

		case "serial_input":

			$scid = $_REQUEST["scid"];
			$sc = get_open_serial_campaign($scid);
			if ($scid && $sc) {
				$smarty_object->assign("sc", $sc);
				$smarty_object->display("html/campaign/serial_input.html");
			} else {
				$smarty_object->display("html/data/error.html");
				break;
			}
			break;

		case "serial_check":
			//期間外の場合エラー

			$scid = $_REQUEST["scid"];
			$sc = get_open_serial_campaign($scid);

			if (!$scid && !$sc) {
				$smarty_object->display("html/data/error.html");
				break;
			}

			$smarty_object->assign("sc", $sc);
			$serial_cd = $_REQUEST["serial_cd"];
			$serial_num = mb_strlen($serial_cd);
			if ($serial_num > 0 && $serial_num == $sc["serial_figure"]) {

				$str = "SELECT * FROM serial_campaign WHERE serial = '{$serial_cd}' AND serial_campaign_master_id = {$scid}";
				logger($str);
				$db = db_connect_common_db();
				$result_array = search_set_query($db, $str);
				db_close($db);
				if (isset($result_array[0])) {
					//user_idがある場合アイテム取得済シリアルコード
					if (isset($result_array[0]['uid']) && strlen($result_array[0]['uid']) > 0) {
						$smarty_object->assign('strError', RTranslator::instance()->t('すでにアイテムを取得した,シリアルコードです。'));
						$smarty_object->assign('serial_cd', $serial_cd);
						$smarty_object->display("html/campaign/serial_input.html");
						break;
					} else {
						//アイテムゲット
						$str = array();
						$str_arithgame = array();
						//serial_campaign にuser_id,get_date をupdate
						$str_arithgame[] = "UPDATE serial_campaign SET uid = '{$user_foundation["uid"]}' , get_date = '".date("Y-m-d H:i:s")."' , target_app_id = '".PLATFORM_APP_ID."' , app_type = ".PLATFORM_TYPE." WHERE serial = '{$serial_cd}' AND serial_campaign_master_id = {$scid}";
						//付与アイテム取得
						$item_detail_info = $gpdo->findItemById($sc['item_id']);

						$userUtil = new UserUtil($gpdo, $user_foundation);

						$gpdo->beginTransaction();

						// プレゼント付与
						$present_detail = '(title:'.$sc['title'].', magazine_name:'.$sc['magazin_name'].', item_id:'.$sc['item_id'].')';	// 管理画面でどういった経緯でプレゼントされたか確認するメモ
						$userUtil->grantPresent(
							$sc['item_id'],
							$sc['title'],
							RTranslator::instance()->t('[x]を取得しました', array('[x]' => $present_detail))
						);

						$qm_arithgame = new QueryManagerArithGame();
						logger(print_r($str_arithgame,true));
						$result_arithgame = $qm_arithgame->do_pg_modify_tran($str_arithgame);
						if (!$result_arithgame) {
							logger("arithgame db update failure");
							//失敗再度入力させる
							$smarty_object->assign('strError', RTranslator::instance()->t('更新に失敗しました。再度シリアルコードを入力し送信ボタンを押してください。'));
							$smarty_object->assign('serial_cd', $serial_cd);
							$smarty_object->display("html/campaign/serial_input.html");
							break;
						}

						$result = $gpdo->commit();

						if ($result) {
							//成功
							$smarty_object->assign("item_info", $item_detail_info);
							$smarty_object->display("html/campaign/serial_success.html");
							break;
						} else {
							//失敗再度入力させる
							logger("app failure*************************************");
							$smarty_object->assign('strError', RTranslator::instance()->t('更新に失敗しました。再度シリアルコードを入力し送信ボタンを押してください。'));
							$smarty_object->assign('serial_cd', $serial_cd);
							$smarty_object->display("html/campaign/serial_input.html");
							break;
						}
					}
				} else {
					$smarty_object->assign('strError', RTranslator::instance()->t('入力したシリアルコードに誤りがある可能性があります。もう一度ご入力ください。'));
					$smarty_object->assign('serial_cd', $serial_cd);
					$smarty_object->display("html/campaign/serial_input.html");
					break;
				}
				$smarty_object->display("html/data/error.html");
				break;
			} else {
				$smarty_object->assign('strError', RTranslator::instance()->t('シリアルコードの桁数に誤りがあります。もう一度ご入力ください。'));
				$smarty_object->assign('serial_cd', $serial_cd);
				$smarty_object->display("html/campaign/serial_input.html");
			}

			break;

		default:
			// (存在しない動作)
			$smarty_object->display("html/data/error.html");
			exit;
	}

} catch (Exception $e) {
	if (isset($gpdo) && $gpdo instanceof GalaxyPDO && $gpdo->inTransaction()) {
		// ロールバック
		$gpdo->rollBack();
	}

	error_log($e);
	register_system_error_message($e);
	$smarty_object->display("html/data/error.html");
	exit;
}
?>
