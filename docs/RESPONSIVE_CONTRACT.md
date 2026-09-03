# Responsive Contract / 响应式契约

## English

`public/assets/responsive.css` is the single canonical owner for viewport-specific layout. `app.css` remains the historical component stylesheet; new responsive fixes should not be appended there.

Header behavior:
- Above 1050px: full desktop navigation and full language names.
- 1050px and below: one-row compact header with `EN / 简 / 繁 / ES`, notification control, and hamburger menu.
- The hamburger panel uses a thin user-name row plus one horizontal action row (Sales: 4 actions; Admin: 5 actions); it closes on route selection, outside click, Escape, or return to desktop width.
- The header and normal page layout must not create document-level horizontal overflow.

Dashboard/control behavior:
- 1120px and below: page-heading controls may move below the heading.
- 860px and below: tablet control groups use deliberate grid/wrap layouts.
- 680px and below: date/range controls use phone-safe layouts and the Admin sticky range becomes a compact multi-row control.
- 480px and below: secondary forms/actions may stack to a single column.
- Wide tables/charts scroll inside their own containers; they must never widen the page itself.

## 中文

`public/assets/responsive.css` 是所有视口相关布局的唯一统一控制层。`app.css` 保留为历史组件样式表；以后新的响应式修复不要继续追加到 `app.css` 中。

Header 行为：
- 大于 1050px：显示完整桌面导航与完整语言名称。
- 1050px 及以下：顶栏保持单行，语言显示 `EN / 简 / 繁 / ES`，并保留通知按钮与汉堡菜单。
- 汉堡菜单展开后使用“紧凑用户名行 + 单行横向操作菜单”（Sales 4 项、Admin 5 项）；选择链接、点击外部、按 Escape 或恢复桌面宽度时自动关闭。
- Header 与普通页面布局不得产生页面级横向滚动。

Dashboard/控制区行为：
- 1120px 及以下：标题右侧控制可以移动到标题下方。
- 860px 及以下：平板控制组使用明确的 grid/wrap 布局。
- 680px 及以下：日期/Range 控件采用手机安全布局，Admin sticky range 变为紧凑多行控制。
- 480px 及以下：次要表单和操作按钮可以堆叠成单列。
- 宽表格/图表只能在自己的容器内横向滚动，不允许撑宽整个页面。
