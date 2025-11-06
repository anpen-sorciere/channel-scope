# ChannelScope 開発用 README

## 🚀 概要

**ChannelScope（チャンネルスコープ）** は、  
YouTube・TikTokのチャンネル成長を分析・改善提案するクリエイター支援アプリです。  
本書は開発者向けのセットアップ手順と構成情報をまとめています。

---

## 🧱 環境情報

| 項目 | 内容 |
|------|------|
| OS | Windows 10 / 11 |
| 開発環境 | XAMPP（PHP + Apache + MySQL） |
| PHP | 8.2 以上推奨 |
| MySQL | 8.x |
| Webサーバ | Apache |
| エディタ | VS Code 推奨 |

---

## 📂 ディレクトリ構成

```plaintext
C:\xampp\htdocs\ChannelScope\
├── app/                # アプリケーション本体
├── public/             # 公開ディレクトリ
├── resources/          # ビュー/JS/CSS
├── routes/             # ルーティング
├── docs/               # ドキュメント類
│   ├── ChannelScope仕様.md
│   ├── README.md
│   └── API設計.md
└── .env                # 環境変数設定
