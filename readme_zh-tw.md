# CC SVG Sprite Manager

CC SVG Sprite Manager 是用來管理 SVG icon source 並產生 SVG sprite 的 WordPress 外掛。

## 功能

- 可上傳單一 SVG，或單次選取最多 100 個 SVG 檔。
- 上傳前會檢查每個檔案，並自動分批上傳以避開 PHP 檔案數量限制。
- 將上傳的個別 SVG icon 保存在外掛目錄 `assets/sprite/icons/`。
- 在外掛目錄產生 `assets/sprite/cc-icons-sprite.svg`。
- 在外掛目錄產生 `assets/sprite/cc-icons-sprite.txt`。
- 同步一份 `cc-icons-sprite.svg` 到目前 theme 的 `assets/images/`，供 theme hook 讀取。
- 在 theme 的 `assets/images/` 產生 `cc-icons-sprite.txt`，列出 icon 清單、引用方式與 hook 說明。
- 匯入時會保留既有 icon。
- 同檔名 icon 可選擇覆蓋、略過，或自動改名為 `name-1.svg`、`name-2.svg`。
- 在 WordPress 後台預覽目前 icon list。
- 可刪除選取的 source icon，並重新產生 sprite。
- 可用單一按鈕全選或取消全選 icon。
- 可用 `[icon]` shortcode 輸出 sprite icon。

## 使用方式

1. 啟用外掛。
2. 到 WordPress 後台的 **工具 → CC SVG Sprite 管理**。
3. 上傳 SVG 檔案，單次最多 100 個。
4. 在 theme 讀取 `wp-content/themes/classic-x/assets/images/cc-icons-sprite.svg`，或使用 `[icon]` shortcode。

範例：

```text
[icon name="search" class="icon icon--search" size="24"]
```

## 檔案位置

- 個別 source icon：`wp-content/plugins/cc-svg-sprite-manager/assets/sprite/icons/`
- 外掛主 sprite：`wp-content/plugins/cc-svg-sprite-manager/assets/sprite/cc-icons-sprite.svg`
- 外掛說明檔：`wp-content/plugins/cc-svg-sprite-manager/assets/sprite/cc-icons-sprite.txt`
- theme 可讀取的 sprite 副本：`wp-content/themes/classic-x/assets/images/cc-icons-sprite.svg`
- theme 說明檔：`wp-content/themes/classic-x/assets/images/cc-icons-sprite.txt`

外掛解除安裝時會刻意保留 theme 內的 `cc-icons-sprite.svg`，讓 theme 在移除外掛後仍可讀取既有 sprite。

## 授權

GPLv2 or later。
