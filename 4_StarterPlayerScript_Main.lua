-- ============================================================
--  SNG BATTLEGROUND | StarterPlayerScript_Main (LocalScript)
--  StarterPlayerScripts > StarterPlayerScript_Main
--  Görev: Karakter seçim ekranı, HUD, yetenek çubuğu,
--         admin mesajları, yükseltme paneli
-- ============================================================

local Players           = game:GetService("Players")
local ReplicatedStorage = game:GetService("ReplicatedStorage")
local TweenService      = game:GetService("TweenService")
local UserInputService  = game:GetService("UserInputService")
local RunService        = game:GetService("RunService")

local player    = Players.LocalPlayer
local playerGui = player.PlayerGui

local Remotes          = ReplicatedStorage:WaitForChild("Remotes")
local selectCharRemote = Remotes:WaitForChild("SelectCharacter")
local useSkillRemote   = Remotes:WaitForChild("UseSkill")
local upgradeRemote    = Remotes:WaitForChild("UpgradeCharacter")
local getDataRemote    = Remotes:WaitForChild("GetPlayerData")
local charSelectedEvt  = Remotes:WaitForChild("CharacterSelected")
local versionUpgEvt    = Remotes:WaitForChild("VersionUpgraded")
local adminMsgEvt      = Remotes:WaitForChild("AdminMessage")
local notifyEvt        = Remotes:WaitForChild("ShowNotification")
local syncStatsEvt     = Remotes:WaitForChild("SyncStats")

-- ─── Renk Paleti ─────────────────────────────────────────────
local C = {
	bg     = Color3.fromRGB(4,4,12),
	panel  = Color3.fromRGB(9,9,22),
	panel2 = Color3.fromRGB(14,14,30),
	border = Color3.fromRGB(30,30,58),
	accent = Color3.fromRGB(115,75,255),
	gold   = Color3.fromRGB(255,205,70),
	red    = Color3.fromRGB(220,55,55),
	green  = Color3.fromRGB(80,220,120),
	text   = Color3.fromRGB(238,232,255),
	muted  = Color3.fromRGB(120,112,155),
	v1     = Color3.fromRGB(80,125,255),
	v2     = Color3.fromRGB(172,58,255),
	v3     = Color3.fromRGB(255,42,42),
	leg    = Color3.fromRGB(255,205,70),
}

-- ─── Yetenek Tanımları ────────────────────────────────────────
local SKILL_DEFS = {
	V1 = {
		{key="Q", name="Zanpakuto Slash",  color=C.v1,                          cd=5 },
		{key="E", name="Byakurai",         color=Color3.fromRGB(200,215,255),    cd=8 },
		{key="R", name="Kyoka Suigetsu",   color=C.v2,                          cd=16},
	},
	V2 = {
		{key="Q", name="Empowered Slash",  color=C.v2,                          cd=5 },
		{key="E", name="Raikoho",          color=Color3.fromRGB(190,100,255),   cd=9 },
		{key="R", name="Kyoka Suigetsu II",color=C.v2,                          cd=15},
		{key="F", name="Kurohitsugi",      color=Color3.fromRGB(82,0,185),      cd=22},
		{key="Z", name="Fragor",           color=Color3.fromRGB(205,85,255),    cd=13},
	},
	V3 = {
		{key="Q", name="Hogyoku Slash",       color=C.v3,                       cd=4 },
		{key="E", name="Hogyoku Beam",        color=Color3.fromRGB(255,80,80),  cd=9 },
		{key="R", name="Absolute Hypnosis",   color=C.v3,                       cd=15},
		{key="F", name="Kurohitsugi Release", color=Color3.fromRGB(100,0,225),  cd=23},
		{key="Z", name="Reconstruction",      color=Color3.fromRGB(255,90,90),  cd=12},
		{key="X", name="★ BANKAI",            color=C.gold,                     cd=32},
	},
}

-- ─── Karakter Veritabanı ─────────────────────────────────────
local CHARS = {
	{
		id="AIZEN", name="Sosuke Aizen",
		desc="5. Bölük eski kaptanı.\nMükemmel hipnozun ustası.\nTüm güçlerin ötesinde.",
		rarity="LEGENDARY", rarityColor=C.leg,
		price=2500, icon="⚔",
		versions={
			{v="V1", label="Shinigami Arc",   color=C.v1, req="2.500 Coin",                    skills=3},
			{v="V2", label="TYBW Arc",        color=C.v2, req="10.000 Coin + Hogyoku Parçası", skills=5},
			{v="V3", label="Hogyoku Füzyonu", color=C.v3, req="25.000 Coin + Hogyoku Füzyonu", skills=6},
		},
	},
	{id="CS1", name="Yakında", desc="Yeni bir savaşçı geliyor...",   rarity="KİLİTLİ", rarityColor=Color3.fromRGB(55,55,65), locked=true, icon="?"},
	{id="CS2", name="Yakında", desc="Ölçülemez bir güç uyuyor...", rarity="KİLİTLİ", rarityColor=Color3.fromRGB(55,55,65), locked=true, icon="?"},
}

-- ─── UI Yardımcıları ─────────────────────────────────────────
local function tw(obj, info, props) TweenService:Create(obj, info, props):Play() end
local function inst(cls, props, parent)
	local o = Instance.new(cls)
	for k, v in pairs(props) do o[k] = v end
	if parent then o.Parent = parent end
	return o
end
local function corner(r, parent) return inst("UICorner", {CornerRadius=UDim.new(0,r)}, parent) end
local function stroke(color, thick, alpha, parent)
	return inst("UIStroke", {Color=color, Thickness=thick, Transparency=alpha or 0}, parent)
end
local function grad(c0, c1, rot, parent)
	return inst("UIGradient", {
		Color=ColorSequence.new({
			ColorSequenceKeypoint.new(0, c0),
			ColorSequenceKeypoint.new(1, c1),
		}), Rotation=rot or 90}, parent)
end
local function pulse(lbl, color, period)
	period = period or 1.4
	task.spawn(function()
		while lbl and lbl.Parent do
			tw(lbl, TweenInfo.new(period/2, Enum.EasingStyle.Sine, Enum.EasingDirection.InOut),
				{TextColor3=color:Lerp(Color3.new(1,1,1), 0.38)})
			task.wait(period/2); if not lbl.Parent then break end
			tw(lbl, TweenInfo.new(period/2, Enum.EasingStyle.Sine, Enum.EasingDirection.InOut),
				{TextColor3=color})
			task.wait(period/2)
		end
	end)
end

-- ─── ScreenGui ───────────────────────────────────────────────
local sg = inst("ScreenGui", {
	Name="ShinigamiUI", ResetOnSpawn=false,
	ZIndexBehavior=Enum.ZIndexBehavior.Sibling,
	IgnoreGuiInset=true,
}, playerGui)

-- ════════════════════════════════════════════════════════════
--  SEÇİM EKRANI
-- ════════════════════════════════════════════════════════════
local selScreen = inst("Frame", {
	Name="SelectionScreen", Size=UDim2.fromScale(1,1),
	BackgroundColor3=C.bg, BorderSizePixel=0, ZIndex=10}, sg)
grad(Color3.fromRGB(7,4,22), C.bg, 135, selScreen)

-- Yıldız arka planı
local starCanvas = inst("Frame", {Size=UDim2.fromScale(1,1), BackgroundTransparency=1, ZIndex=11}, selScreen)
task.spawn(function()
	while selScreen and selScreen.Parent and selScreen.Visible do
		task.wait(math.random()*0.5+0.1)
		local s = inst("Frame", {
			Size=UDim2.new(0,math.random(1,3),0,math.random(1,3)),
			Position=UDim2.new(math.random(),0,math.random(),0),
			BackgroundColor3=Color3.fromRGB(180+math.random()*75, 170+math.random()*85, 255),
			BackgroundTransparency=0.3+math.random()*0.5,
			BorderSizePixel=0, ZIndex=12}, starCanvas)
		corner(2, s)
		tw(s, TweenInfo.new(3+math.random()*3, Enum.EasingStyle.Quad), {
			BackgroundTransparency=1,
			Position=s.Position - UDim2.new(0,0,0.08+math.random()*0.1,0)})
		game:GetService("Debris"):AddItem(s, 7)
	end
end)

-- ─── Başlık ──────────────────────────────────────────────────
local titleFrame = inst("Frame", {
	Size=UDim2.new(0.80,0,0.10,0), Position=UDim2.new(0.10,0,0.01,0),
	BackgroundTransparency=1, ZIndex=13}, selScreen)

inst("TextLabel", {
	Size=UDim2.fromScale(1,0.65), BackgroundTransparency=1,
	Text="SNG BATTLEGROUND", Font=Enum.Font.GothamBold, TextScaled=true,
	TextColor3=C.text, TextXAlignment=Enum.TextXAlignment.Center,
	TextStrokeColor3=C.accent, TextStrokeTransparency=0.35, ZIndex=14}, titleFrame)

inst("TextLabel", {
	Size=UDim2.fromScale(1,0.35), Position=UDim2.fromScale(0,0.65),
	BackgroundTransparency=1, Text="SAVAŞÇINI SEÇ",
	Font=Enum.Font.Gotham, TextScaled=true,
	TextColor3=C.muted, TextXAlignment=Enum.TextXAlignment.Center, ZIndex=14}, titleFrame)

local titleLine = inst("Frame", {
	Size=UDim2.new(0.5,0,0,2), Position=UDim2.new(0.25,0,1,-2),
	BackgroundColor3=C.accent, BackgroundTransparency=0.45, BorderSizePixel=0, ZIndex=14}, titleFrame)
corner(2, titleLine)

-- ─── Coin Rozeti ─────────────────────────────────────────────
local coinBadge = inst("Frame", {
	Size=UDim2.new(0.18,0,0.055,0), Position=UDim2.new(0.80,0,0.02,0),
	BackgroundColor3=C.panel2, BackgroundTransparency=0.1, BorderSizePixel=0, ZIndex=15}, selScreen)
corner(10, coinBadge); stroke(C.gold, 1.5, 0.42, coinBadge)
inst("TextLabel", {Size=UDim2.new(0.22,0,1,0), BackgroundTransparency=1,
	Text="🪙", TextScaled=true, ZIndex=16}, coinBadge)
local coinLabel = inst("TextLabel", {
	Size=UDim2.new(0.78,0,1,0), Position=UDim2.new(0.22,0,0,0),
	BackgroundTransparency=1, Text="0", Font=Enum.Font.GothamBold, TextScaled=true,
	TextColor3=C.gold, TextXAlignment=Enum.TextXAlignment.Left, ZIndex=16}, coinBadge)

-- ─── Karakter Kartları ────────────────────────────────────────
local cardsContainer = inst("Frame", {
	Name="CardsContainer", Size=UDim2.new(0.96,0,0.40,0), Position=UDim2.new(0.02,0,0.11,0),
	BackgroundTransparency=1, ZIndex=12, ClipsDescendants=true}, selScreen)
inst("UIListLayout", {
	FillDirection=Enum.FillDirection.Horizontal,
	HorizontalAlignment=Enum.HorizontalAlignment.Center,
	VerticalAlignment=Enum.VerticalAlignment.Center,
	Padding=UDim.new(0.015,0)}, cardsContainer)

-- ─── Detay Paneli ────────────────────────────────────────────
local detailPanel = inst("Frame", {
	Name="DetailPanel", Size=UDim2.new(0.96,0,0.24,0), Position=UDim2.new(0.02,0,0.52,0),
	BackgroundColor3=C.panel, BackgroundTransparency=0.08, BorderSizePixel=0, ZIndex=12}, selScreen)
corner(16, detailPanel); stroke(C.border, 1.5, 0.2, detailPanel)
grad(C.panel, Color3.fromRGB(7,6,22), 180, detailPanel)

local accentLine = inst("Frame", {
	Size=UDim2.new(0,0,0,2), Position=UDim2.new(0.05,0,0,0),
	BackgroundColor3=C.accent, BorderSizePixel=0, ZIndex=13}, detailPanel)
corner(2, accentLine)

local detailName = inst("TextLabel", {
	Size=UDim2.new(0.35,0,0.35,0), Position=UDim2.new(0.02,0,0.06,0),
	BackgroundTransparency=1, Text="Karakter Seç",
	Font=Enum.Font.GothamBold, TextScaled=true,
	TextColor3=C.text, TextXAlignment=Enum.TextXAlignment.Left, ZIndex=13}, detailPanel)

local detailRarity = inst("TextLabel", {
	Size=UDim2.new(0.35,0,0.18,0), Position=UDim2.new(0.02,0,0.41,0),
	BackgroundTransparency=1, Text="",
	Font=Enum.Font.GothamBold, TextScaled=true,
	TextColor3=C.gold, TextXAlignment=Enum.TextXAlignment.Left, ZIndex=13}, detailPanel)

local detailDesc = inst("TextLabel", {
	Size=UDim2.new(0.35,0,0.34,0), Position=UDim2.new(0.02,0,0.60,0),
	BackgroundTransparency=1, Text="",
	Font=Enum.Font.Gotham, TextScaled=true,
	TextColor3=C.muted, TextXAlignment=Enum.TextXAlignment.Left,
	TextYAlignment=Enum.TextYAlignment.Top, TextWrapped=true, ZIndex=13}, detailPanel)

local verFrame = inst("Frame", {
	Size=UDim2.new(0.60,0,1,0), Position=UDim2.new(0.38,0,0,0),
	BackgroundTransparency=1, ZIndex=13}, detailPanel)
inst("UIListLayout", {
	FillDirection=Enum.FillDirection.Horizontal,
	HorizontalAlignment=Enum.HorizontalAlignment.Center,
	VerticalAlignment=Enum.VerticalAlignment.Center,
	Padding=UDim.new(0.02,0)}, verFrame)

-- ─── Savaşa Gir Butonu ───────────────────────────────────────
local selectBtn = inst("TextButton", {
	Name="SelectBtn", Size=UDim2.new(0.38,0,0.055,0), Position=UDim2.new(0.31,0,0.77,0),
	BackgroundColor3=C.accent, BorderSizePixel=0,
	Text="▶  SAVAŞA GİR", Font=Enum.Font.GothamBold,
	TextScaled=true, TextColor3=Color3.new(1,1,1), ZIndex=14}, selScreen)
corner(12, selectBtn)
grad(Color3.fromRGB(138,88,255), Color3.fromRGB(88,48,200), 90, selectBtn)
selectBtn.MouseEnter:Connect(function()
	tw(selectBtn, TweenInfo.new(0.18), {BackgroundColor3=Color3.fromRGB(148,98,255)})
end)
selectBtn.MouseLeave:Connect(function()
	tw(selectBtn, TweenInfo.new(0.18), {BackgroundColor3=C.accent})
end)

-- State
local selectedChar    = nil
local selectedVersion = "V1"
local cardFrames      = {}
local verBtns         = {}

-- ─── Versiyon Kartları ────────────────────────────────────────
local function buildVersionCards(charData)
	for _, b in pairs(verBtns) do b:Destroy() end; verBtns = {}
	tw(accentLine, TweenInfo.new(0.5, Enum.EasingStyle.Expo), {Size=UDim2.new(0.88,0,0,2)})
	if not charData or charData.locked then return end

	for _, ver in ipairs(charData.versions or {}) do
		local isV1 = (ver.v == "V1")
		local card = inst("TextButton", {
			Name="Ver_"..ver.v, Size=UDim2.new(0.30,0,0.88,0),
			BackgroundColor3=C.panel2, BackgroundTransparency=isV1 and 0 or 0.3,
			BorderSizePixel=0, Text="", ZIndex=14}, verFrame)
		corner(12, card)
		local cs = stroke(ver.color, 1.8, isV1 and 0.05 or 0.5, card)

		inst("Frame", {Size=UDim2.new(1,0,0,3), BackgroundColor3=ver.color,
			BackgroundTransparency=0.35, BorderSizePixel=0, ZIndex=15}, card)

		inst("TextLabel", {Size=UDim2.new(1,0,0.40,0), Position=UDim2.new(0,0,0.05,0),
			BackgroundTransparency=1, Text=ver.v,
			Font=Enum.Font.GothamBold, TextScaled=true, TextColor3=ver.color, ZIndex=15}, card)
		inst("TextLabel", {Size=UDim2.new(0.92,0,0.22,0), Position=UDim2.new(0.04,0,0.44,0),
			BackgroundTransparency=1, Text=ver.label,
			Font=Enum.Font.GothamBold, TextScaled=true, TextColor3=C.text, TextWrapped=true, ZIndex=15}, card)
		inst("TextLabel", {Size=UDim2.new(0.92,0,0.18,0), Position=UDim2.new(0.04,0,0.66,0),
			BackgroundTransparency=1, Text=ver.skills.." Yetenek",
			Font=Enum.Font.Gotham, TextScaled=true, TextColor3=ver.color, ZIndex=15}, card)

		if ver.v ~= "V1" then
			inst("TextLabel", {Size=UDim2.new(0.3,0,0.22,0), Position=UDim2.new(0.70,0,0.04,0),
				BackgroundTransparency=1, Text="🔒", TextScaled=true, ZIndex=16}, card)
		end

		card.MouseEnter:Connect(function()
			tw(card, TweenInfo.new(0.2), {BackgroundTransparency=0})
			tw(cs,   TweenInfo.new(0.2), {Transparency=0})
		end)
		card.MouseLeave:Connect(function()
			if selectedVersion ~= ver.v then
				tw(card, TweenInfo.new(0.2), {BackgroundTransparency=0.3})
				tw(cs,   TweenInfo.new(0.2), {Transparency=0.5})
			end
		end)
		card.MouseButton1Click:Connect(function()
			selectedVersion = ver.v
			for _, b2 in pairs(verBtns) do
				local s2 = b2:FindFirstChildOfClass("UIStroke")
				tw(b2, TweenInfo.new(0.18), {BackgroundTransparency=0.3})
				if s2 then tw(s2, TweenInfo.new(0.18), {Transparency=0.5}) end
			end
			tw(card, TweenInfo.new(0.18), {BackgroundTransparency=0})
			tw(cs,   TweenInfo.new(0.18), {Transparency=0})
		end)
		table.insert(verBtns, card)
	end
end

local function updateDetail(charData)
	selectedChar    = charData
	selectedVersion = "V1"
	detailName.Text   = charData.name
	detailRarity.Text = "◆ " .. charData.rarity
	detailRarity.TextColor3 = charData.rarityColor
	detailDesc.Text   = charData.desc or ""
	buildVersionCards(charData)
	if charData.locked then
		selectBtn.Text             = "YAKINDA"
		selectBtn.BackgroundColor3 = Color3.fromRGB(30,28,40)
	else
		selectBtn.Text             = "▶  SAVAŞA GİR"
		selectBtn.BackgroundColor3 = C.accent
	end
end

-- ─── Karakter Kartları ────────────────────────────────────────
for _, ch in ipairs(CHARS) do
	local card = inst("TextButton", {
		Name="Card_"..ch.id, Size=UDim2.new(0.28,0,1,0),
		BackgroundColor3=C.panel, BackgroundTransparency=0.05,
		BorderSizePixel=0, Text="", ZIndex=12}, cardsContainer)
	corner(18, card)
	local cs = stroke(ch.rarityColor, 2, ch.locked and 0.7 or 0.35, card)
	grad(C.panel, Color3.fromRGB(6,5,18), 180, card)

	inst("Frame", {Size=UDim2.new(1,0,0.025,0), BackgroundColor3=ch.rarityColor,
		BackgroundTransparency=ch.locked and 0.9 or 0.2, BorderSizePixel=0, ZIndex=13}, card)

	local portrait = inst("Frame", {
		Size=UDim2.new(0.92,0,0.55,0), Position=UDim2.new(0.04,0,0.03,0),
		BackgroundColor3=ch.locked and Color3.fromRGB(12,10,22) or ch.rarityColor:Lerp(C.bg,0.88),
		BorderSizePixel=0, ZIndex=13}, card)
	corner(12, portrait)

	local iconLbl = inst("TextLabel", {
		Size=UDim2.fromScale(1,1), BackgroundTransparency=1,
		Text=ch.icon or "?", Font=Enum.Font.GothamBold, TextScaled=true,
		TextColor3=ch.locked and Color3.fromRGB(35,33,48) or ch.rarityColor:Lerp(Color3.new(1,1,1),0.35),
		ZIndex=14}, portrait)
	if not ch.locked then pulse(iconLbl, ch.rarityColor:Lerp(Color3.new(1,1,1),0.3), 2) end

	local badgeF = inst("Frame", {
		Size=UDim2.new(0.72,0,0.18,0), Position=UDim2.new(0.04,0,0.04,0),
		BackgroundColor3=ch.rarityColor, BackgroundTransparency=ch.locked and 0.85 or 0.28,
		BorderSizePixel=0, ZIndex=15}, portrait)
	corner(6, badgeF)
	inst("TextLabel", {Size=UDim2.fromScale(1,1), BackgroundTransparency=1,
		Text=ch.rarity, Font=Enum.Font.GothamBold, TextScaled=true,
		TextColor3=Color3.new(1,1,1), ZIndex=16}, badgeF)

	inst("TextLabel", {
		Size=UDim2.new(0.92,0,0.14,0), Position=UDim2.new(0.04,0,0.60,0),
		BackgroundTransparency=1, Text=ch.name,
		Font=Enum.Font.GothamBold, TextScaled=true,
		TextColor3=ch.locked and C.muted or C.text,
		TextXAlignment=Enum.TextXAlignment.Left, TextWrapped=true, ZIndex=13}, card)

	if not ch.locked and ch.price and ch.price > 0 then
		inst("TextLabel", {
			Size=UDim2.new(0.92,0,0.10,0), Position=UDim2.new(0.04,0,0.74,0),
			BackgroundTransparency=1, Text="🪙 " .. tostring(ch.price) .. " (V1)",
			Font=Enum.Font.GothamBold, TextScaled=true,
			TextColor3=C.gold, TextXAlignment=Enum.TextXAlignment.Left, ZIndex=13}, card)
	end

	if not ch.locked and ch.versions then
		local pillRow = inst("Frame", {
			Size=UDim2.new(0.92,0,0.09,0), Position=UDim2.new(0.04,0,0.86,0),
			BackgroundTransparency=1, ZIndex=13}, card)
		inst("UIListLayout", {
			FillDirection=Enum.FillDirection.Horizontal,
			Padding=UDim.new(0.04,0)}, pillRow)
		local pClrs = {C.v1, C.v2, C.v3}
		for vi, ver in ipairs(ch.versions) do
			local pill = inst("Frame", {
				Size=UDim2.new(0.30,0,1,0), BackgroundColor3=pClrs[vi],
				BackgroundTransparency=0.5, BorderSizePixel=0, ZIndex=14}, pillRow)
			corner(5, pill)
			inst("TextLabel", {Size=UDim2.fromScale(1,1), BackgroundTransparency=1,
				Text=ver.v, Font=Enum.Font.GothamBold, TextScaled=true,
				TextColor3=Color3.new(1,1,1), ZIndex=15}, pill)
		end
	end

	card.MouseEnter:Connect(function()
		if not ch.locked then
			tw(card, TweenInfo.new(0.2), {BackgroundColor3=ch.rarityColor:Lerp(C.panel,0.92)})
			tw(cs,   TweenInfo.new(0.2), {Transparency=0})
		end
	end)
	card.MouseLeave:Connect(function()
		tw(card, TweenInfo.new(0.2), {BackgroundColor3=C.panel})
		tw(cs,   TweenInfo.new(0.2), {Transparency=ch.locked and 0.7 or 0.35})
	end)
	card.MouseButton1Click:Connect(function()
		if ch.locked then return end
		for _, cf in pairs(cardFrames) do tw(cf, TweenInfo.new(0.18), {BackgroundColor3=C.panel}) end
		tw(card, TweenInfo.new(0.18), {BackgroundColor3=ch.rarityColor:Lerp(C.panel,0.86)})
		updateDetail(ch)
	end)
	cardFrames[ch.id] = card
end

selectBtn.MouseButton1Click:Connect(function()
	if not selectedChar or selectedChar.locked then return end
	selectCharRemote:FireServer(selectedChar.id, selectedVersion)
	tw(selScreen, TweenInfo.new(0.55, Enum.EasingStyle.Quad), {BackgroundTransparency=1})
	task.wait(0.6); selScreen.Visible = false
end)

-- ════════════════════════════════════════════════════════════
--  HUD
-- ════════════════════════════════════════════════════════════
local hudFrame = inst("Frame", {
	Name="HUD", Size=UDim2.fromScale(1,1),
	BackgroundTransparency=1, Visible=false, ZIndex=5}, sg)

-- HP Barı
local hpBg = inst("Frame", {
	Size=UDim2.new(0.30,0,0.026,0), Position=UDim2.new(0.02,0,0.945,0),
	BackgroundColor3=Color3.fromRGB(14,4,4), BackgroundTransparency=0.15,
	BorderSizePixel=0, ZIndex=6}, hudFrame)
corner(9, hpBg); stroke(Color3.fromRGB(40,10,10), 1, 0.6, hpBg)

local hpBar = inst("Frame", {
	Size=UDim2.fromScale(1,1), BackgroundColor3=Color3.fromRGB(55,200,55),
	BorderSizePixel=0, ZIndex=7}, hpBg)
corner(9, hpBar); grad(Color3.fromRGB(80,220,80), Color3.fromRGB(40,170,40), 0, hpBar)

local hpLabel = inst("TextLabel", {
	Size=UDim2.new(0.30,0,0.026,0), Position=UDim2.new(0.02,0,0.916,0),
	BackgroundTransparency=1, Text="HP  250 / 250",
	Font=Enum.Font.GothamBold, TextScaled=true,
	TextColor3=C.text, TextXAlignment=Enum.TextXAlignment.Left, ZIndex=6}, hudFrame)

-- Karakter / Versiyon Etiketi
local charLabel = inst("TextLabel", {
	Size=UDim2.new(0.22,0,0.032,0), Position=UDim2.new(0.39,0,0.84,0),
	BackgroundTransparency=1, Text="",
	Font=Enum.Font.GothamBold, TextScaled=true,
	TextColor3=C.text, TextXAlignment=Enum.TextXAlignment.Center, ZIndex=6}, hudFrame)

-- ─── Yetenek Çubuğu ──────────────────────────────────────────
local skillBar = inst("Frame", {
	Name="SkillBar", Size=UDim2.new(0.60,0,0.10,0), Position=UDim2.new(0.20,0,0.87,0),
	BackgroundColor3=C.panel, BackgroundTransparency=0.2, BorderSizePixel=0, ZIndex=6}, hudFrame)
corner(18, skillBar); stroke(C.border, 1.5, 0.3, skillBar)
grad(C.panel, Color3.fromRGB(6,5,18), 180, skillBar)
inst("UIListLayout", {
	FillDirection=Enum.FillDirection.Horizontal,
	HorizontalAlignment=Enum.HorizontalAlignment.Center,
	VerticalAlignment=Enum.VerticalAlignment.Center,
	Padding=UDim.new(0.012,0)}, skillBar)

local skillSlots = {}
local cdConns    = {}
local localCDs   = {}

local function buildSkillBar(version)
	for _, c in pairs(cdConns) do pcall(function() c:Disconnect() end) end
	for _, s in pairs(skillSlots) do if s.Parent then s:Destroy() end end
	cdConns = {}; skillSlots = {}; localCDs = {}

	local defs = SKILL_DEFS[version]
	if not defs then return end

	local n     = #defs
	local barW  = math.min(0.60, 0.09*n + 0.02)
	tw(skillBar, TweenInfo.new(0.35, Enum.EasingStyle.Back), {
		Size=UDim2.new(barW, 0, 0.10, 0),
		Position=UDim2.new((1-barW)/2, 0, 0.87, 0)})

	for i, def in ipairs(defs) do
		local slot = inst("Frame", {
			Name="Slot_"..def.key, Size=UDim2.new(1/n - 0.015, 0, 0.84, 0),
			BackgroundColor3=C.panel2, BackgroundTransparency=0.1,
			BorderSizePixel=0, ZIndex=7}, skillBar)
		corner(12, slot)
		local ss = stroke(def.color, 2, 0.2, slot)
		grad(def.color:Lerp(C.panel2, 0.88), C.panel2, 180, slot)

		-- Tuş etiketi
		inst("TextLabel", {
			Size=UDim2.new(1,0,0.45,0), BackgroundTransparency=1,
			Text=def.key, Font=Enum.Font.GothamBold, TextScaled=true,
			TextColor3=def.color, ZIndex=8}, slot)

		-- Yetenek ismi
		inst("TextLabel", {
			Size=UDim2.new(1,0,0.28,0), Position=UDim2.new(0,0,0.44,0),
			BackgroundTransparency=1, Text=def.name,
			Font=Enum.Font.Gotham, TextScaled=true, TextWrapped=true,
			TextColor3=C.muted, ZIndex=8}, slot)

		-- Cooldown overlay
		local cdOverlay = inst("Frame", {
			Name="CDOverlay", Size=UDim2.fromScale(1,0), Position=UDim2.new(0,0,1,0),
			BackgroundColor3=Color3.new(0,0,0), BackgroundTransparency=0.35,
			BorderSizePixel=0, ZIndex=9}, slot)
		corner(12, cdOverlay)

		local cdLabel = inst("TextLabel", {
			Name="CDLabel", Size=UDim2.fromScale(1,1), BackgroundTransparency=1,
			Text="", Font=Enum.Font.GothamBold, TextScaled=true,
			TextColor3=Color3.new(1,1,1), ZIndex=10}, cdOverlay)

		-- Mobil dokunma butonu
		if UserInputService.TouchEnabled then
			local touchBtn = inst("TextButton", {
				Size=UDim2.fromScale(1,1), BackgroundTransparency=1,
				Text="", ZIndex=11}, slot)
			touchBtn.MouseButton1Click:Connect(function()
				useSkillRemote:FireServer("AIZEN", version, i)
			end)
		end

		skillSlots[i] = slot

		-- Cooldown animasyonu (her frame)
		local conn = RunService.Heartbeat:Connect(function()
			local last = localCDs[i]
			if not last then
				cdOverlay.Size = UDim2.fromScale(1, 0)
				cdLabel.Text   = ""
				return
			end
			local elapsed = os.clock() - last
			local pct     = math.max(0, 1 - elapsed/def.cd)
			if pct <= 0 then
				localCDs[i]        = nil
				cdOverlay.Size     = UDim2.fromScale(1, 0)
				cdLabel.Text       = ""
				tw(slot, TweenInfo.new(0.12), {BackgroundTransparency=0.1})
				tw(ss,   TweenInfo.new(0.12), {Transparency=0.2})
			else
				cdOverlay.Size     = UDim2.new(1,0, pct, 0)
				cdOverlay.Position = UDim2.new(0,0, 1-pct, 0)
				cdLabel.Text       = string.format("%.1f", def.cd * pct)
				tw(slot, TweenInfo.new(0.05), {BackgroundTransparency=0.45})
				tw(ss,   TweenInfo.new(0.05), {Transparency=0.6})
			end
		end)
		table.insert(cdConns, conn)
	end
end

-- Tuş basınca cooldown başlat
UserInputService.InputBegan:Connect(function(input, gpe)
	if gpe or not skillSlots[1] then return end
	local defs    = SKILL_DEFS[charLabel.Text and charLabel.Text:match("V%d") or "V1"]
	if not defs then return end
	local keyMap  = {Q=1, E=2, R=3, F=4, Z=5, X=6}
	local idx     = keyMap[input.KeyCode.Name]
	if not idx or not defs[idx] then return end
	if not localCDs[idx] then
		localCDs[idx] = os.clock()
	end
end)

-- ═══════════════════════════════════════════════════════════
--  BİLDİRİM SİSTEMİ
-- ═══════════════════════════════════════════════════════════
local notifQueue = {}
local notifBusy  = false

local function showNotif(title, body, color)
	local n = inst("Frame", {
		Size=UDim2.new(0.32,0,0.068,0), Position=UDim2.new(1.05,0,0.02,0),
		BackgroundColor3=C.panel2, BackgroundTransparency=0.05,
		BorderSizePixel=0, ZIndex=30}, sg)
	corner(12, n); stroke(color, 2, 0.25, n)
	grad(color:Lerp(C.panel2, 0.88), C.panel2, 175, n)

	inst("Frame", {Size=UDim2.new(0,4,1,0), BackgroundColor3=color,
		BackgroundTransparency=0.1, BorderSizePixel=0, ZIndex=31}, n)

	inst("TextLabel", {
		Size=UDim2.new(0.9,0,0.45,0), Position=UDim2.new(0.06,0,0.05,0),
		BackgroundTransparency=1, Text=title,
		Font=Enum.Font.GothamBold, TextScaled=true,
		TextColor3=Color3.new(1,1,1), TextXAlignment=Enum.TextXAlignment.Left, ZIndex=31}, n)

	inst("TextLabel", {
		Size=UDim2.new(0.9,0,0.40,0), Position=UDim2.new(0.06,0,0.52,0),
		BackgroundTransparency=1, Text=body,
		Font=Enum.Font.Gotham, TextScaled=true, TextWrapped=true,
		TextColor3=C.muted, TextXAlignment=Enum.TextXAlignment.Left, ZIndex=31}, n)

	-- Kayma animasyonu
	tw(n, TweenInfo.new(0.45, Enum.EasingStyle.Back), {Position=UDim2.new(0.67,0,0.02,0)})
	task.wait(3.8)
	tw(n, TweenInfo.new(0.38, Enum.EasingStyle.Quad), {Position=UDim2.new(1.1,0,0.02,0)})
	task.wait(0.42)
	n:Destroy()
	notifBusy = false
	if #notifQueue > 0 then
		local next = table.remove(notifQueue, 1)
		notifBusy = true
		task.spawn(showNotif, next[1], next[2], next[3])
	end
end

local function queueNotif(title, body, color)
	if notifBusy then
		table.insert(notifQueue, {title, body, color})
	else
		notifBusy = true
		task.spawn(showNotif, title, body, color)
	end
end

-- ─── Admin Mesaj Barı ────────────────────────────────────────
local adminBar = inst("Frame", {
	Name="AdminBar", Size=UDim2.new(0.70,0,0.05,0), Position=UDim2.new(0.15,0,-0.06,0),
	BackgroundColor3=C.panel2, BackgroundTransparency=0.08,
	BorderSizePixel=0, ZIndex=25}, sg)
corner(12, adminBar)
local adminLabel = inst("TextLabel", {
	Size=UDim2.fromScale(1,1), BackgroundTransparency=1,
	Text="", Font=Enum.Font.GothamBold, TextScaled=true,
	TextColor3=Color3.new(1,1,1), ZIndex=26}, adminBar)

local function showAdminMsg(text, color)
	adminBar.BackgroundColor3 = color:Lerp(C.panel2, 0.82)
	stroke(color, 2, 0.2, adminBar)
	adminLabel.Text      = text
	adminLabel.TextColor3 = color
	tw(adminBar, TweenInfo.new(0.38, Enum.EasingStyle.Back), {Position=UDim2.new(0.15,0,0.01,0)})
	task.delay(5.5, function()
		tw(adminBar, TweenInfo.new(0.32, Enum.EasingStyle.Quad), {Position=UDim2.new(0.15,0,-0.06,0)})
	end)
end

adminMsgEvt.OnClientEvent:Connect(function(text, color)
	showAdminMsg(text, color)
	queueNotif("SNG Admin", text, color)
end)

notifyEvt.OnClientEvent:Connect(function(title, body, color)
	queueNotif(title, body, color)
end)

-- ─── Karakter Seçildi / Versiyon Yükseltildi ─────────────────
charSelectedEvt.OnClientEvent:Connect(function(charId, version)
	hudFrame.Visible = true
	charLabel.Text   = charId .. " " .. version
	local clrMap     = {V1=C.v1, V2=C.v2, V3=C.v3}
	charLabel.TextColor3 = clrMap[version] or C.text
	buildSkillBar(version)

	local vLabels = {
		V1 = "Shinigami Arc",
		V2 = "TYBW Arc",
		V3 = "Hogyoku Füzyonu",
	}
	queueNotif("Karakter Seçildi",
		charId .. " " .. version .. " — " .. (vLabels[version] or ""),
		clrMap[version] or C.accent)

	-- Coin senkronizasyonu
	task.spawn(function()
		local data = getDataRemote:InvokeServer()
		if data then
			coinLabel.Text = tostring(data.coins)
		end
	end)
end)

versionUpgEvt.OnClientEvent:Connect(function(charId, newVer)
	charLabel.Text = charId .. " " .. newVer
	buildSkillBar(newVer)
	queueNotif("Versiyon Yükseltildi!", charId .. " → " .. newVer, C.green)
end)

-- ─── HP Güncelleme ───────────────────────────────────────────
local function updateHpBar(hp, maxHp)
	local pct = math.clamp(hp/maxHp, 0, 1)
	tw(hpBar, TweenInfo.new(0.22, Enum.EasingStyle.Quad), {Size=UDim2.fromScale(pct,1)})
	local col = pct > 0.55 and Color3.fromRGB(55,200,55)
		or pct > 0.25 and Color3.fromRGB(240,200,40)
		or Color3.fromRGB(220,50,50)
	tw(hpBar, TweenInfo.new(0.22), {BackgroundColor3=col})
	hpLabel.Text = string.format("HP  %d / %d", hp, maxHp)
end

-- Stat senkronizasyon eventi
syncStatsEvt.OnClientEvent:Connect(function(stats)
	if stats then
		updateHpBar(stats.hp or 1, stats.maxHp or 1)
	end
end)

-- RunService ile HP barı güncelleme (yerel karakter)
RunService.Heartbeat:Connect(function()
	local char = player.Character
	if not char then return end
	local hum = char:FindFirstChild("Humanoid")
	if not hum then return end
	if hum.MaxHealth <= 0 then return end
	updateHpBar(hum.Health, hum.MaxHealth)
end)

-- ─── Yükseltme Paneli (HUD üzerinde düğme) ───────────────────
local upgradeBtn = inst("TextButton", {
	Name="UpgradeBtn",
	Size=UDim2.new(0.10,0,0.044,0), Position=UDim2.new(0.84,0,0.905,0),
	BackgroundColor3=C.gold, BackgroundTransparency=0.1,
	Text="⬆ YÜKSELTİL", Font=Enum.Font.GothamBold, TextScaled=true,
	TextColor3=Color3.fromRGB(14,10,4), BorderSizePixel=0, ZIndex=7, Visible=false}, hudFrame)
corner(10, upgradeBtn)

local upgPanelOpen = false
upgradeBtn.MouseButton1Click:Connect(function()
	upgPanelOpen = not upgPanelOpen
	-- Basit modal göster
	local data = getDataRemote:InvokeServer()
	if not data then return end
	if data.character ~= "AIZEN" then return end

	local modal = inst("Frame", {
		Size=UDim2.new(0.4,0,0.45,0),
		Position=UDim2.new(0.30,0,0.25,0),
		BackgroundColor3=C.panel, BackgroundTransparency=0.06,
		BorderSizePixel=0, ZIndex=20}, sg)
	corner(18, modal); stroke(C.gold, 2, 0.3, modal)
	grad(C.panel, Color3.fromRGB(7,6,22), 180, modal)

	inst("TextLabel", {Size=UDim2.new(1,0,0.18,0), BackgroundTransparency=1,
		Text="Aizen Yükseltme", Font=Enum.Font.GothamBold, TextScaled=true,
		TextColor3=C.gold, ZIndex=21}, modal)

	local function upgRow(targetVer, reqText, color, yPos)
		local row = inst("Frame", {
			Size=UDim2.new(0.90,0,0.20,0), Position=UDim2.new(0.05,0,yPos,0),
			BackgroundColor3=color:Lerp(C.panel2,0.85), BackgroundTransparency=0.1,
			BorderSizePixel=0, ZIndex=21}, modal)
		corner(10, row); stroke(color, 1.5, 0.35, row)

		inst("TextLabel", {Size=UDim2.new(0.55,0,1,0), BackgroundTransparency=1,
			Text=targetVer .. "   " .. reqText, Font=Enum.Font.Gotham, TextScaled=true,
			TextColor3=C.text, TextXAlignment=Enum.TextXAlignment.Left, ZIndex=22}, row)

		local btn = inst("TextButton", {
			Size=UDim2.new(0.38,0,0.7,0), Position=UDim2.new(0.60,0,0.15,0),
			BackgroundColor3=color, BackgroundTransparency=0.2, BorderSizePixel=0,
			Text="Yükselt", Font=Enum.Font.GothamBold, TextScaled=true,
			TextColor3=Color3.new(1,1,1), ZIndex=22}, row)
		corner(8, btn)
		btn.MouseButton1Click:Connect(function()
			upgradeRemote:FireServer("AIZEN", targetVer)
			modal:Destroy()
		end)
	end

	upgRow("V2", "10K Coin + Hogyoku Parçası", C.v2, 0.20)
	upgRow("V3", "25K Coin + Hogyoku Füzyonu",  C.v3, 0.45)

	inst("TextLabel", {Size=UDim2.new(0.9,0,0.12,0), Position=UDim2.new(0.05,0,0.72,0),
		BackgroundTransparency=1,
		Text="🪙 Coins: " .. tostring(data.coins) ..
			"   🔮 HogyokuPiece: " .. tostring(data.inventory.HogyokuPiece or 0) ..
			"   ✨ HogyokuFusion: " .. tostring(data.inventory.HogyokuFusion or 0),
		Font=Enum.Font.Gotham, TextScaled=true,
		TextColor3=C.muted, ZIndex=21}, modal)

	local closeBtn = inst("TextButton", {
		Size=UDim2.new(0.28,0,0.12,0), Position=UDim2.new(0.36,0,0.86,0),
		BackgroundColor3=C.red, BackgroundTransparency=0.2, BorderSizePixel=0,
		Text="Kapat", Font=Enum.Font.GothamBold, TextScaled=true,
		TextColor3=Color3.new(1,1,1), ZIndex=21}, modal)
	corner(10, closeBtn)
	closeBtn.MouseButton1Click:Connect(function() modal:Destroy() end)
end)

-- Upgrade butonu göster (karakter seçildikten sonra)
charSelectedEvt.OnClientEvent:Connect(function(charId, version)
	if charId == "AIZEN" and version ~= "V3" then
		upgradeBtn.Visible = true
	else
		upgradeBtn.Visible = false
	end
end)

-- ─── Başlangıç coin güncellemesi ────────────────────────────
task.spawn(function()
	task.wait(2)
	local data = getDataRemote:InvokeServer()
	if data then coinLabel.Text = tostring(data.coins) end
end)

print("[SNG BATTLEGROUND] StarterPlayerScript_Main yüklendi ✓")
