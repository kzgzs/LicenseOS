# LicenseOS Git 仓库初始化指南

## 一、.gitignore 配置

项目根目录已存在 `.gitignore` 文件，当前内容覆盖 Laravel 标准忽略规则。

### 当前已有规则

```
/node_modules
/public/hot
/public/storage
/storage/*.key
/vendor
.env
.env.backup
.env.production
.phpunit.result.cache
Homestead.json
Homestead.yaml
auth.json
npm-debug.log
yarn-error.log
/.fleet
/.idea
/.vscode
*.log
screenshot_*
配置文件.txt
```

### 建议补充的规则

以下规则建议追加到现有 `.gitignore` 中：

```
# OS 生成文件
.DS_Store
Thumbs.db

# 编辑器临时文件
*.swp
*.swo
*~

# Laravel 构建产物
/public/build

# 临时/调试文件
*.tmp
*.new
```

---

## 二、Git 初始化完整步骤

### 前置条件

确保已安装 Git（`git --version` 检查）。如未安装：

- Windows：从 https://git-scm.com/download/win 下载
- macOS：`brew install git`
- Linux：`sudo apt install git`

### Step 1：进入项目目录

```bash
cd E:\LicenseOS
```

### Step 2：初始化 Git 仓库

```bash
git init
```

这会在项目根目录创建 `.git` 隐藏文件夹。

### Step 3：确认 .gitignore 生效

```bash
git status
```

确认 `vendor/`、`.env`、`node_modules/` 等目录/文件不在跟踪列表中。

### Step 4：添加所有文件到暂存区

```bash
git add .
```

### Step 5：首次提交

```bash
git commit -m "feat: LicenseOS V3.4 初始提交"
```

### Step 6：配置用户信息（如未配置过）

```bash
git config --global user.name "你的名字"
git config --global user.email "your@email.com"
```

### Step 7：连接远程仓库

**GitHub：**

```bash
git remote add origin git@github.com:你的用户名/LicenseOS.git
```

**Gitee：**

```bash
git remote add origin git@gitee.com:你的用户名/LicenseOS.git
```

> 先在 GitHub/Gitee 上创建空仓库，不要勾选"初始化 README"。

### Step 8：推送到远程

```bash
git branch -M main
git push -u origin main
```

---

## 三、日常使用命令速查

### 基本工作流

```bash
# 查看修改了哪些文件
git status

# 查看具体改动内容
git diff

# 添加所有改动到暂存区
git add .

# 提交改动（写清楚改了什么）
git commit -m "fix: 修复授权注入检测正则"

# 推送到远程
git push
```

### 分支管理

```bash
# 创建新功能分支
git checkout -b feature/新功能名

# 切回主分支
git checkout main

# 合并功能分支到主分支
git merge feature/新功能名

# 删除已合并的分支
git branch -d feature/新功能名
```

### 撤销操作

```bash
# 撤销工作区修改（未 add）
git checkout -- 文件名

# 撤销暂存区（已 add 但未 commit）
git reset HEAD 文件名

# 回退到上一次提交（保留修改在工作区）
git reset --soft HEAD~1
```

### 查看历史

```bash
# 查看提交记录
git log --oneline

# 查看某个文件的修改历史
git log -p -- 文件路径
```

### 拉取远程更新

```bash
# 如果在其他设备修改了代码，拉取最新版本
git pull origin main
```

---

## 四、注意事项

1. **永远不要提交 `.env` 文件**，它包含数据库密码、API Key 等敏感信息
2. **`vendor/` 目录不要提交**，用 `composer install` 恢复
3. **`storage/` 下的日志和缓存不需要提交**，已通过 .gitignore 排除
4. 每次提交尽量做到**一个功能一个 commit**，方便回滚
5. 提交信息建议使用中文描述清楚改了什么，方便日后查找