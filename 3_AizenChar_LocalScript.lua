-- ============================================================
--  SNG BATTLEGROUND | AizenChar (LocalScript)
--  StarterPlayerScripts > AizenChar_LocalScript
--  Görev: Yetenek girişleri, VFX efektleri, aura sistemi
--  V1: 3 yetenek | V2: 5 yetenek | V3: 6 yetenek
-- ============================================================

local Players           = game:GetService("Players")
local ReplicatedStorage = game:GetService("ReplicatedStorage")
local TweenService      = game:GetService("TweenService")
local RunService        = game:GetService("RunService")
local SoundService      = game:GetService("SoundService")
local Lighting          = game:GetService("Lighting")
local UserInputService  = game:GetService("UserInputService")

local player = Players.LocalPlayer
local camera = workspace.CurrentCamera

local Remotes         = ReplicatedStorage:WaitForChild("Remotes")
local vfxRemote       = Remotes:WaitForChild("PlayVFX")
local charSelectedEvt = Remotes:WaitForChild("CharacterSelected")
local versionUpgEvt   = Remotes:WaitForChild("VersionUpgraded")
local useSkillRemote  = Remotes:WaitForChild("UseSkill")
local syncStatsEvt    = Remotes:WaitForChild("SyncStats")

local VFXFolder = workspace:FindFirstChild("VFX")
if not VFXFolder then
	VFXFolder = Instance.new("Folder")
	VFXFolder.Name   = "VFX"
	VFXFolder.Parent = workspace
end

local origAmbient    = Lighting.Ambient
local origOutdoor    = Lighting.OutdoorAmbient
local origBrightness = Lighting.Brightness

-- ─── Durum ───────────────────────────────────────────────────
local currentChar    = nil
local currentVersion = "V1"
local localCooldowns = {}

-- ─── Sesler ──────────────────────────────────────────────────
local SOUNDS = {
	slash      = "rbxassetid://9120432866",
	beam       = "rbxassetid://9120386460",
	explosion  = "rbxassetid://9120242410",
	hypnosis   = "rbxassetid://1369158732",
	transform  = "rbxassetid://4865205613",
	bankai     = "rbxassetid://4504539975",
	whoosh     = "rbxassetid://4905547301",
	lightning  = "rbxassetid://9119736244",
	dark_pulse = "rbxassetid://3740760060",
	power_up   = "rbxassetid://4612720692",
	hogyoku    = "rbxassetid://4504540358",
}

local function playSound(id, vol, pitch, pos)
	local s = Instance.new("Sound")
	s.SoundId            = id
	s.Volume             = vol or 1
	s.PlaybackSpeed      = pitch or 1
	s.RollOffMaxDistance = 80
	if pos then
		local anchor = Instance.new("Part")
		anchor.Anchored     = true
		anchor.CanCollide   = false
		anchor.Transparency = 1
		anchor.Size         = Vector3.new(0.1, 0.1, 0.1)
		anchor.CFrame       = CFrame.new(pos)
		anchor.Parent       = workspace
		s.Parent            = anchor
		game:GetService("Debris"):AddItem(anchor, 5)
	else
		s.Parent = SoundService
	end
	s:Play()
	game:GetService("Debris"):AddItem(s, 6)
end

-- ─── Yardımcılar ─────────────────────────────────────────────
local function tw(obj, info, props) TweenService:Create(obj, info, props):Play() end
local function db(obj, t)           game:GetService("Debris"):AddItem(obj, t) end

local function part(props, parent)
	local p = Instance.new("Part")
	p.Anchored   = true
	p.CanCollide = false
	p.CastShadow = false
	p.Material   = Enum.Material.Neon
	for k, v in pairs(props) do p[k] = v end
	p.Parent = parent or VFXFolder
	return p
end

-- ─── Ekran Sarsıntısı ────────────────────────────────────────
local _sm = 0
RunService.RenderStepped:Connect(function()
	if _sm > 0.001 then
		camera.CFrame = camera.CFrame * CFrame.Angles(
			(math.random() - 0.5) * _sm,
			(math.random() - 0.5) * _sm, 0)
		_sm = _sm * 0.86
	end
end)
local function shake(mag, dur)
	_sm = mag
	task.delay(dur, function() _sm = 0 end)
end

-- ─── Işık Flaşı ──────────────────────────────────────────────
local function lFlash(color, dur)
	Lighting.Ambient        = color
	Lighting.OutdoorAmbient = color
	Lighting.Brightness     = 3.8
	task.delay(dur, function()
		tw(Lighting, TweenInfo.new(dur * 2.5), {
			Ambient        = origAmbient,
			OutdoorAmbient = origOutdoor,
			Brightness     = origBrightness,
		})
	end)
end

-- ─── VFX Primitifleri ────────────────────────────────────────
local function burst(origin, color, n, spd, life, s0, s1)
	s0 = s0 or 0.35; s1 = s1 or 0.04
	for i = 1, n do
		local p = part({Color=color, Size=Vector3.new(s0,s0,s0), Transparency=0.1,
			Shape=Enum.PartType.Ball, CFrame=CFrame.new(origin)})
		local d = Vector3.new(math.random()-0.5, math.random()*0.75+0.35, math.random()-0.5).Unit
			* spd * (0.4 + math.random() * 0.6)
		tw(p, TweenInfo.new(life, Enum.EasingStyle.Quad, Enum.EasingDirection.Out),
			{CFrame=CFrame.new(origin+d*life), Size=Vector3.new(s1,s1,s1), Transparency=1})
		db(p, life + 0.05)
	end
end

local function ring(center, color, r0, r1, dur, a0)
	a0 = a0 or 0.15
	local p = part({Color=color, Size=Vector3.new(0.25, r0*2, r0*2), Transparency=a0,
		Shape=Enum.PartType.Cylinder,
		CFrame=CFrame.new(center+Vector3.new(0,0.12,0))*CFrame.Angles(0,0,math.pi/2)})
	tw(p, TweenInfo.new(dur, Enum.EasingStyle.Quad, Enum.EasingDirection.Out),
		{Size=Vector3.new(0.05, r1*2, r1*2), Transparency=1})
	db(p, dur + 0.05)
end

local function pillar(pos, color, w, h, dur)
	local p = part({Color=color, Size=Vector3.new(w,0.1,w), Transparency=0.2, CFrame=CFrame.new(pos)})
	tw(p, TweenInfo.new(dur*0.35, Enum.EasingStyle.Back),
		{Size=Vector3.new(w,h,w), CFrame=CFrame.new(pos+Vector3.new(0,h/2,0))})
	task.delay(dur*0.35, function()
		if p.Parent then
			tw(p, TweenInfo.new(dur*0.65), {Transparency=1, Size=Vector3.new(w*0.2,h*1.1,w*0.2)})
		end
	end)
	db(p, dur + 0.05)
end

local function slash(origin, dir, color, n, len, spread)
	spread = spread or 55
	for i = 1, n do
		local ang = math.rad(-spread/2 + (spread / (math.max(n-1,1))) * (i-1))
		local sd  = CFrame.Angles(0, ang, 0) * dir
		local p   = part({Color=color, Size=Vector3.new(0.18, len*0.28, 0.18), Transparency=0.05,
			CFrame=CFrame.new(origin, origin+sd)*CFrame.new(0,0,-len*0.14)})
		tw(p, TweenInfo.new(0.28, Enum.EasingStyle.Quad),
			{Size=Vector3.new(0.04, len, 0.04), Transparency=1,
			 CFrame=CFrame.new(origin, origin+sd)*CFrame.new(0,0,-len*0.58)})
		db(p, 0.32)
	end
end

local function beam(origin, dir, color, w, len, dur)
	local b = part({Color=color, Size=Vector3.new(w,w,0.1), Transparency=0.06,
		CFrame=CFrame.new(origin, origin+dir)*CFrame.new(0,0,-len/2)})
	tw(b, TweenInfo.new(dur*0.25, Enum.EasingStyle.Quad), {Size=Vector3.new(w*1.2, w*1.2, len)})
	task.delay(dur*0.25, function()
		if b.Parent then
			tw(b, TweenInfo.new(dur*0.75), {Transparency=1, Size=Vector3.new(0.05,0.05,len*1.1)})
		end
	end)
	db(b, dur + 0.05)
	local g = part({Color=color:Lerp(Color3.new(1,1,1),0.4),
		Size=Vector3.new(w*3, w*3, len*0.92), Transparency=0.65,
		CFrame=CFrame.new(origin, origin+dir)*CFrame.new(0,0,-len/2)})
	tw(g, TweenInfo.new(dur), {Transparency=1}); db(g, dur + 0.05)
end

local function sphere(center, color, maxS, dur, a0)
	a0 = a0 or 0.15
	local p = part({Color=color, Size=Vector3.new(1,1,1), Transparency=a0,
		Shape=Enum.PartType.Ball, CFrame=CFrame.new(center)})
	tw(p, TweenInfo.new(dur, Enum.EasingStyle.Quad, Enum.EasingDirection.Out),
		{Size=Vector3.new(maxS,maxS,maxS), Transparency=1})
	db(p, dur + 0.05)
end

local function lightning(top, bot, color, segs, dur)
	segs = segs or 12
	local step  = (bot - top) / segs
	local parts = {}
	for i = 0, segs-1 do
		local a = top + step*i     + Vector3.new((math.random()-0.5)*2.2, 0, (math.random()-0.5)*2.2)
		local b = top + step*(i+1) + Vector3.new((math.random()-0.5)*2.2, 0, (math.random()-0.5)*2.2)
		local mid = (a+b)/2
		local len = (b-a).Magnitude
		local p = part({Color=color, Size=Vector3.new(0.14, len, 0.14), Transparency=0.08,
			CFrame=CFrame.new(mid, b)*CFrame.Angles(math.pi/2, 0, 0)})
		table.insert(parts, p)
	end
	task.delay(dur, function()
		for _, p in ipairs(parts) do
			if p.Parent then
				tw(p, TweenInfo.new(0.08), {Transparency=1}); db(p, 0.1)
			end
		end
	end)
	for _, p in ipairs(parts) do db(p, dur + 0.18) end
end

local function shard(pos, color, size, dur)
	local p = part({Color=color, Size=Vector3.new(size,size,size), Transparency=0.08,
		CFrame=CFrame.new(pos)*CFrame.Angles(
			math.random()*math.pi, math.random()*math.pi, math.random()*math.pi)})
	tw(p, TweenInfo.new(dur, Enum.EasingStyle.Quad), {Size=Vector3.new(0.05,0.05,0.05), Transparency=1})
	db(p, dur + 0.05)
end

local function spawnSpiralOrbs(center, color, count, radius, height, dur)
	for i = 1, count do
		local ang0 = (math.pi*2/count)*i
		local orb  = part({Color=color, Size=Vector3.new(0.58,0.58,0.58), Transparency=0.14,
			Shape=Enum.PartType.Ball,
			CFrame=CFrame.new(center + Vector3.new(math.cos(ang0)*radius, 0, math.sin(ang0)*radius))})
		local t = 0; local conn
		conn = RunService.Heartbeat:Connect(function(dt)
			t += dt
			if not orb.Parent then conn:Disconnect() return end
			local ang = ang0 + t*3.5
			local h   = math.sin(t*2) * height
			orb.CFrame = CFrame.new(center + Vector3.new(math.cos(ang)*radius, h, math.sin(ang)*radius))
		end)
		task.delay(dur, function()
			conn:Disconnect()
			if orb.Parent then
				tw(orb, TweenInfo.new(0.3), {Transparency=1, Size=Vector3.new(0.05,0.05,0.05)})
				db(orb, 0.35)
			end
		end)
	end
end

-- ─── Cutscene Sistemi ────────────────────────────────────────
local function cutscene(title, sub, color, hold)
	hold = hold or 2.2
	local sg = player.PlayerGui:FindFirstChild("ShinigamiUI")
	if not sg then return end

	local frame = Instance.new("Frame")
	frame.Size                  = UDim2.fromScale(1, 1)
	frame.BackgroundColor3      = Color3.new(0, 0, 0)
	frame.BackgroundTransparency = 1
	frame.BorderSizePixel       = 0
	frame.ZIndex                = 200
	frame.Parent                = sg
	tw(frame, TweenInfo.new(0.18), {BackgroundTransparency=0.1})

	local function bar(anchorBot)
		local b = Instance.new("Frame", frame)
		b.Size            = UDim2.new(1, 0, 0, 0)
		b.BackgroundColor3 = Color3.new(0, 0, 0)
		b.BorderSizePixel = 0
		b.ZIndex          = 201
		if anchorBot then b.Position = UDim2.new(0, 0, 1, 0) end
		tw(b, TweenInfo.new(0.48, Enum.EasingStyle.Quint),
			anchorBot and {Size=UDim2.new(1,0,0,92), Position=UDim2.new(0,0,1,-92)}
			          or  {Size=UDim2.new(1,0,0,92)})
		return b
	end
	bar(false); bar(true)
	task.wait(0.32)

	local line = Instance.new("Frame", frame)
	line.Size             = UDim2.new(0, 0, 0, 2)
	line.AnchorPoint      = Vector2.new(0.5, 0.5)
	line.Position         = UDim2.new(0.5, 0, 0.5, -1)
	line.BackgroundColor3 = color
	line.BorderSizePixel  = 0
	line.ZIndex           = 202
	tw(line, TweenInfo.new(0.5, Enum.EasingStyle.Expo), {Size=UDim2.new(0.62, 0, 0, 2)})
	task.wait(0.18)

	local function lbl(text, size, yOff, font, useStroke)
		local l = Instance.new("TextLabel", frame)
		l.Size                  = UDim2.new(1, 0, 0, size+10)
		l.Position              = UDim2.new(0, 0, 0.5, yOff)
		l.BackgroundTransparency = 1
		l.Text                  = text
		l.Font                  = font or Enum.Font.GothamBold
		l.TextSize              = size
		l.TextColor3            = Color3.new(1, 1, 1)
		l.TextTransparency      = 1
		l.ZIndex                = 203
		if useStroke then
			l.TextStrokeColor3       = color
			l.TextStrokeTransparency = 0.15
		end
		return l
	end

	local t1 = lbl(title,  62, -82, Enum.Font.GothamBold, true)
	local t2 = lbl(sub or "", 21,  22, Enum.Font.Gotham,  false)
	t2.TextColor3 = color

	tw(t1, TweenInfo.new(0.52, Enum.EasingStyle.Back), {TextTransparency=0})
	task.wait(0.22)
	tw(t2, TweenInfo.new(0.4),  {TextTransparency=0})
	task.wait(hold)

	for _, o in ipairs(frame:GetDescendants()) do
		pcall(function()
			tw(o, TweenInfo.new(0.3), {BackgroundTransparency=1, TextTransparency=1})
		end)
	end
	tw(frame, TweenInfo.new(0.35), {BackgroundTransparency=1})
	db(frame, 0.5)
end

-- ─── Aura Sistemi ────────────────────────────────────────────
local AURA = {
	V1 = {color=Color3.fromRGB(80,120,255),  n=4, r=2.8, spd=1.4, sz=0.38},
	V2 = {color=Color3.fromRGB(170,55,255),  n=6, r=3.4, spd=2.0, sz=0.48},
	V3 = {color=Color3.fromRGB(255,38,38),   n=9, r=4.2, spd=2.5, sz=0.62},
}
local auraConn, auraOrbs = nil, {}

local function stopAura()
	if auraConn then auraConn:Disconnect(); auraConn = nil end
	for _, o in ipairs(auraOrbs) do if o.Parent then o:Destroy() end end
	auraOrbs = {}
end

local function startAura(version)
	stopAura()
	local cfg = AURA[version] or AURA.V1

	for i = 1, cfg.n do
		local orb = part({Color=cfg.color, Size=Vector3.new(cfg.sz,cfg.sz,cfg.sz),
			Transparency=0.28, Shape=Enum.PartType.Ball})
		part({Color=cfg.color:Lerp(Color3.new(1,1,1),0.38),
			Size=Vector3.new(cfg.sz*2.6,cfg.sz*2.6,cfg.sz*2.6),
			Transparency=0.72, Shape=Enum.PartType.Ball, Parent=orb})
		table.insert(auraOrbs, orb)
	end

	local t = 0
	auraConn = RunService.Heartbeat:Connect(function(dt)
		t += dt
		local char = player.Character
		if not char then return end
		local root = char:FindFirstChild("HumanoidRootPart")
		if not root then return end

		for i, orb in ipairs(auraOrbs) do
			if not orb.Parent then continue end
			local ph  = (math.pi*2/cfg.n) * (i-1)
			local ang = t * cfg.spd + ph
			local h   = math.sin(t*1.8 + ph) * 1.6
			local pos = root.Position + Vector3.new(math.cos(ang)*cfg.r, h+2.0, math.sin(ang)*cfg.r)
			orb.CFrame = CFrame.new(pos)
			local g = orb:FindFirstChildOfClass("Part")
			if g then g.CFrame = orb.CFrame end
		end

		-- V2: mor kıvılcımlar
		if version == "V2" and math.random() < 0.04 then
			local r2 = player.Character and player.Character:FindFirstChild("HumanoidRootPart")
			if r2 then
				local sp = part({Color=cfg.color, Size=Vector3.new(0.15,0.15,0.15),
					Transparency=0.15, Shape=Enum.PartType.Ball,
					CFrame=CFrame.new(r2.Position + Vector3.new((math.random()-0.5)*4, 0.1, (math.random()-0.5)*4))})
				tw(sp, TweenInfo.new(0.5), {CFrame=sp.CFrame+Vector3.new(0,math.random()*4+1,0),
					Transparency=1, Size=Vector3.new(0.02,0.02,0.02)})
				db(sp, 0.55)
			end
		end

		-- V3: kırmızı/turuncu alev parçacıkları
		if version == "V3" and math.random() < 0.07 then
			local r2 = player.Character and player.Character:FindFirstChild("HumanoidRootPart")
			if r2 then
				local col = math.random() < 0.5 and Color3.fromRGB(255,38,38) or Color3.fromRGB(255,160,30)
				local sp  = part({Color=col, Size=Vector3.new(0.18,0.18,0.18),
					Transparency=0.12, Shape=Enum.PartType.Ball,
					CFrame=CFrame.new(r2.Position + Vector3.new((math.random()-0.5)*5.5, 0.1, (math.random()-0.5)*5.5))})
				tw(sp, TweenInfo.new(0.48), {CFrame=sp.CFrame+Vector3.new(0,math.random()*4.5+1,0),
					Transparency=1, Size=Vector3.new(0.02,0.02,0.02)})
				db(sp, 0.52)
			end
		end
	end)
end

-- ─── Hogyoku Dönüşüm Animasyonu ──────────────────────────────
local function hogyokuTransform(char)
	local root = char:FindFirstChild("HumanoidRootPart"); if not root then return end
	local pos  = root.Position

	playSound(SOUNDS.hogyoku, 1.1, 0.9, pos)
	cutscene("HOGYOKU FÜZYONU", "Tam Dönüşüm", Color3.fromRGB(255,25,25), 3.2)
	shake(0.42, 3.8); lFlash(Color3.fromRGB(75,0,0), 0.38)

	for i = 1, 12 do task.delay(i*0.08, function()
		ring(pos, Color3.fromRGB(185,0,0), 22-i*1.7, 1, 0.6, 0.38)
	end) end

	local core = part({Color=Color3.fromRGB(16,0,26), Size=Vector3.new(1,1,1),
		Transparency=0.04, Shape=Enum.PartType.Ball, CFrame=CFrame.new(pos+Vector3.new(0,3,0))})
	tw(core, TweenInfo.new(1.8, Enum.EasingStyle.Quad), {Size=Vector3.new(20,20,20), Transparency=0.2})
	spawnSpiralOrbs(pos+Vector3.new(0,3,0), Color3.fromRGB(200,20,255), 6, 7, 2.5, 1.8)
	task.wait(1.8)

	playSound(SOUNDS.explosion, 1.2, 0.8, pos); shake(0.6, 2.8); lFlash(Color3.fromRGB(110,0,0), 0.55)
	tw(core, TweenInfo.new(0.28, Enum.EasingStyle.Back), {Size=Vector3.new(38,38,38), Transparency=1})
	db(core, 0.35)

	burst(pos+Vector3.new(0,3,0), Color3.fromRGB(255,35,35),  280, 65, 3.2, 0.68, 0.06)
	burst(pos+Vector3.new(0,3,0), Color3.fromRGB(255,160,0),  145, 48, 2.6, 0.52, 0.05)
	burst(pos+Vector3.new(0,3,0), Color3.fromRGB(140,0,230),  100, 42, 2.2, 0.42, 0.04)
	sphere(pos+Vector3.new(0,2,0), Color3.fromRGB(255,28,28),  60, 1.8, 0.07)
	sphere(pos+Vector3.new(0,2,0), Color3.fromRGB(205,0,0),    40, 1.2, 0.05)

	for i = 1, 16 do task.delay(i*0.09, function()
		ring(pos, Color3.fromRGB(255,45,45), 1, 7+i*5, 1.5)
	end) end

	for i = 1, 12 do task.delay(0.1+i*0.08, function()
		local a = math.rad(i*30)
		pillar(pos+Vector3.new(math.cos(a)*12, 0, math.sin(a)*12), Color3.fromRGB(225,25,25), 3, 36, 2.8)
	end) end

	for i = 1, 10 do task.delay(i*0.14, function()
		local a = math.rad(i*36+math.random()*28)
		lightning(pos+Vector3.new(math.cos(a)*10,34,math.sin(a)*10),
			pos+Vector3.new(math.cos(a)*10,0,math.sin(a)*10),
			Color3.fromRGB(255,75,75), 18, 0.42)
	end) end

	task.delay(0.9, function()
		local hog = part({Color=Color3.fromRGB(200,20,255), Size=Vector3.new(2.5,2.5,2.5),
			Transparency=0.05, Shape=Enum.PartType.Ball, CFrame=CFrame.new(pos+Vector3.new(0,7,0))})
		local pt = 0; local hConn
		hConn = RunService.Heartbeat:Connect(function(dt)
			pt += dt
			if not hog.Parent then hConn:Disconnect() return end
			hog.CFrame = CFrame.new(pos+Vector3.new(0, 7+math.sin(pt*2.2)*0.45, 0))
		end)
		task.delay(3.5, function()
			hConn:Disconnect()
			if hog.Parent then
				tw(hog, TweenInfo.new(0.6), {Transparency=1, Size=Vector3.new(0.1,0.1,0.1)}); db(hog, 0.7)
			end
		end)
	end)

	task.delay(0.65, function() startAura("V3") end)
end

-- ═══════════════════════════════════════════════════════════
--  V1 YETENEKLERİ
-- ═══════════════════════════════════════════════════════════
local function v1_Q(char)
	local root = char:FindFirstChild("HumanoidRootPart"); if not root then return end
	local pos  = root.Position; local fwd = root.CFrame.LookVector
	playSound(SOUNDS.slash, 0.85, 1.1, pos); shake(0.07, 0.4)
	slash(pos+fwd*2, fwd, Color3.fromRGB(140,175,255), 5, 8, 55)
	burst(pos+fwd*5, Color3.fromRGB(100,145,255), 22, 14, 0.48, 0.28, 0.04)
	ring(pos, Color3.fromRGB(80,120,255), 0.5, 6, 0.5)
end

local function v1_E(char)
	local root = char:FindFirstChild("HumanoidRootPart"); if not root then return end
	local pos  = root.Position; local fwd = root.CFrame.LookVector
	playSound(SOUNDS.beam, 0.9, 1.2, pos); shake(0.09, 0.55)
	beam(pos+fwd*2+Vector3.new(0,1.4,0), fwd, Color3.fromRGB(210,215,255), 1.4, 48, 0.58)
	lightning(pos+fwd*6+Vector3.new(0,10,0), pos+fwd*6, Color3.fromRGB(200,215,255), 11, 0.32)
	burst(pos+fwd*46, Color3.fromRGB(215,220,255), 30, 16, 0.58, 0.34, 0.05)
end

local function v1_R(char)
	local root = char:FindFirstChild("HumanoidRootPart"); if not root then return end
	local pos  = root.Position
	playSound(SOUNDS.hypnosis, 0.9, 1.0, pos)
	cutscene("Kyoka Suigetsu", "Kır...", Color3.fromRGB(160,95,255), 1.8)
	shake(0.14, 1.2); lFlash(Color3.fromRGB(32,0,65), 0.16)
	for i = 1, 7 do task.delay(i*0.1, function()
		ring(pos, Color3.fromRGB(160,75,255), 1+i, 4+i*2.8, 0.98)
	end) end
	burst(pos, Color3.fromRGB(180,100,255), 65, 24, 1.15, 0.44, 0.06)
	burst(pos+Vector3.new(0,1.6,0), Color3.fromRGB(120,55,200), 35, 18, 0.88, 0.32, 0.04)
	for i = 1, 6 do task.delay(i*0.11, function()
		local a = math.rad(i*60)
		slash(pos+Vector3.new(math.cos(a)*4,1,math.sin(a)*4),
			Vector3.new(math.cos(a),0,math.sin(a)), Color3.fromRGB(200,150,255), 3, 6, 42)
	end) end
end

-- ═══════════════════════════════════════════════════════════
--  V2 YETENEKLERİ
-- ═══════════════════════════════════════════════════════════
local function v2_Q(char)
	local root = char:FindFirstChild("HumanoidRootPart"); if not root then return end
	local pos  = root.Position; local fwd = root.CFrame.LookVector
	playSound(SOUNDS.slash, 1.0, 0.9, pos); shake(0.12, 0.68)
	for i = -1, 1 do task.delay(math.abs(i)*0.07, function()
		slash(pos+fwd*2+Vector3.new(i*1.0,0,0), fwd, Color3.fromRGB(178,72,255), 6, 12, 65)
	end) end
	burst(pos+fwd*7, Color3.fromRGB(200,95,255), 42, 22, 0.68, 0.38, 0.05)
	ring(pos, Color3.fromRGB(180,58,255), 1, 10, 0.72)
	pillar(pos+fwd*9, Color3.fromRGB(160,55,240), 2.4, 15, 0.68)
end

local function v2_E(char)
	local root = char:FindFirstChild("HumanoidRootPart"); if not root then return end
	local pos  = root.Position; local fwd = root.CFrame.LookVector
	playSound(SOUNDS.power_up, 0.9, 1.1, pos); shake(0.17, 1.0); lFlash(Color3.fromRGB(32,0,68), 0.2)
	local ch = part({Color=Color3.fromRGB(155,55,255), Size=Vector3.new(1,1,1),
		Transparency=0.18, Shape=Enum.PartType.Ball,
		CFrame=CFrame.new(pos+fwd*2+Vector3.new(0,1.5,0))})
	tw(ch, TweenInfo.new(0.34, Enum.EasingStyle.Back), {Size=Vector3.new(3.4,3.4,3.4)})
	task.wait(0.34)
	playSound(SOUNDS.beam, 1.0, 0.95, pos)
	tw(ch, TweenInfo.new(0.12), {Transparency=1}); db(ch, 0.15)
	beam(pos+fwd*2+Vector3.new(0,1.5,0), fwd, Color3.fromRGB(178,75,255), 3.2, 54, 0.72)
	for i = 1, 6 do task.delay(i*0.07, function()
		lightning(pos+fwd*(5+i*5)+Vector3.new(0,10,0), pos+fwd*(5+i*5),
			Color3.fromRGB(198,98,255), 11, 0.28)
	end) end
	burst(pos+fwd*52, Color3.fromRGB(200,98,255), 70, 32, 1.0, 0.48, 0.05)
	sphere(pos+fwd*52, Color3.fromRGB(180,55,255), 18, 0.9, 0.2)
end

local function v2_R(char)
	local root = char:FindFirstChild("HumanoidRootPart"); if not root then return end
	local pos  = root.Position
	playSound(SOUNDS.hypnosis, 1.0, 0.85, pos)
	cutscene("Kyoka Suigetsu", "Mükemmel Hipnoz", Color3.fromRGB(198,72,255), 2.2)
	shake(0.22, 1.7); lFlash(Color3.fromRGB(44,0,88), 0.24)
	for i = 1, 12 do task.delay(i*0.09, function()
		ring(pos, Color3.fromRGB(178,55,255), 1, 5+i*4, 1.28)
	end) end
	burst(pos, Color3.fromRGB(200,75,255), 118, 38, 1.7, 0.54, 0.06)
	burst(pos+Vector3.new(0,2,0), Color3.fromRGB(130,38,255), 70, 28, 1.4, 0.44, 0.05)
	sphere(pos, Color3.fromRGB(180,55,255), 34, 1.4, 0.1)
	for i = 1, 9 do task.delay(i*0.09, function()
		local a = math.rad(i*40)
		slash(pos+Vector3.new(math.cos(a)*7,1,math.sin(a)*7),
			Vector3.new(math.cos(a),0,math.sin(a)), Color3.fromRGB(218,138,255), 4, 10, 54)
	end) end
end

local function v2_F(char)
	local root = char:FindFirstChild("HumanoidRootPart"); if not root then return end
	local pos  = root.Position; local fwd = root.CFrame.LookVector
	playSound(SOUNDS.dark_pulse, 1.0, 0.88, pos)
	cutscene("Hadō #90", "Kurohitsugi — Kara Tabut", Color3.fromRGB(55,0,120), 2.3)
	shake(0.26, 2.0); lFlash(Color3.fromRGB(14,0,35), 0.3)
	for i = 1, 8 do task.delay(i*0.09, function()
		ring(pos, Color3.fromRGB(50,0,110), 17-i*1.9, 1, 0.52, 0.4)
	end) end
	task.wait(0.68)
	playSound(SOUNDS.beam, 1.0, 0.8, pos)
	beam(pos+fwd*2+Vector3.new(0,1.5,0), fwd, Color3.fromRGB(78,0,190), 5.0, 58, 1.0)
	task.delay(0.44, function()
		local hit = pos + fwd*55
		playSound(SOUNDS.explosion, 1.1, 0.85, hit)
		for i = 1, 14 do
			shard(hit+Vector3.new((math.random()-0.5)*16, (math.random()-0.5)*10+5,
				(math.random()-0.5)*16), Color3.fromRGB(18,0,40), 4.2, 1.1)
		end
		shake(0.32, 1.2)
		sphere(hit, Color3.fromRGB(65,0,155), 26, 1.2, 0.1)
		burst(hit, Color3.fromRGB(108,0,220), 95, 40, 1.4, 0.54, 0.06)
		for i = 1, 8 do task.delay(i*0.1, function()
			ring(hit, Color3.fromRGB(55,0,130), 1, 5+i*4.5, 1.1)
		end) end
	end)
end

local function v2_Z(char)
	local root = char:FindFirstChild("HumanoidRootPart"); if not root then return end
	local pos  = root.Position; local fwd = root.CFrame.LookVector
	playSound(SOUNDS.explosion, 1.0, 1.05, pos)
	shake(0.20, 1.4); lFlash(Color3.fromRGB(45,0,95), 0.22)
	for i = 1, 5 do task.delay(i*0.12, function()
		burst(pos+fwd*(i*3.5), Color3.fromRGB(200,85,255), 45, 32, 0.9, 0.42, 0.05)
		ring(pos+fwd*(i*3.5), Color3.fromRGB(185,68,255), 0.5, 8+i*2, 0.75)
	end) end
	task.delay(0.65, function()
		sphere(pos+fwd*18, Color3.fromRGB(185,65,255), 22, 1.1, 0.15)
	end)
end

-- ═══════════════════════════════════════════════════════════
--  V3 YETENEKLERİ
-- ═══════════════════════════════════════════════════════════
local function v3_Q(char)
	local root = char:FindFirstChild("HumanoidRootPart"); if not root then return end
	local pos  = root.Position; local fwd = root.CFrame.LookVector
	playSound(SOUNDS.slash, 1.1, 0.88, pos); shake(0.16, 0.9)
	for i = -2, 2 do task.delay(math.abs(i)*0.05, function()
		slash(pos+fwd*2+Vector3.new(i*0.85,0,0), fwd, Color3.fromRGB(255,80,80), 7, 16, 70)
	end) end
	burst(pos+fwd*9, Color3.fromRGB(255,60,60), 55, 28, 0.78, 0.44, 0.05)
	ring(pos, Color3.fromRGB(255,42,42), 1, 13, 0.8)
	pillar(pos+fwd*11, Color3.fromRGB(255,38,38), 3.2, 20, 0.9)
	lFlash(Color3.fromRGB(60,0,0), 0.1)
end

local function v3_E(char)
	local root = char:FindFirstChild("HumanoidRootPart"); if not root then return end
	local pos  = root.Position; local fwd = root.CFrame.LookVector
	playSound(SOUNDS.hogyoku, 1.0, 1.1, pos); shake(0.22, 1.2); lFlash(Color3.fromRGB(55,0,0), 0.25)
	local ch = part({Color=Color3.fromRGB(255,40,40), Size=Vector3.new(1.5,1.5,1.5),
		Transparency=0.1, Shape=Enum.PartType.Ball,
		CFrame=CFrame.new(pos+fwd*2+Vector3.new(0,1.5,0))})
	tw(ch, TweenInfo.new(0.4, Enum.EasingStyle.Back), {Size=Vector3.new(5,5,5)})
	task.wait(0.4)
	playSound(SOUNDS.beam, 1.1, 0.82, pos)
	tw(ch, TweenInfo.new(0.12), {Transparency=1}); db(ch, 0.15)
	beam(pos+fwd*2+Vector3.new(0,1.5,0), fwd, Color3.fromRGB(255,45,45), 4.5, 66, 0.88)
	for i = 1, 8 do task.delay(i*0.07, function()
		lightning(pos+fwd*(4+i*6)+Vector3.new(0,12,0), pos+fwd*(4+i*6),
			Color3.fromRGB(255,90,90), 13, 0.30)
	end) end
	burst(pos+fwd*63, Color3.fromRGB(255,55,55), 100, 55, 1.5, 0.58, 0.06)
	sphere(pos+fwd*63, Color3.fromRGB(255,30,30), 28, 1.1, 0.12)
end

local function v3_R(char)
	local root = char:FindFirstChild("HumanoidRootPart"); if not root then return end
	local pos  = root.Position
	playSound(SOUNDS.hypnosis, 1.1, 0.78, pos)
	cutscene("Mutlak Hipnoz", "Sen zaten yenildin...", Color3.fromRGB(255,42,42), 2.5)
	shake(0.32, 2.2); lFlash(Color3.fromRGB(60,0,0), 0.32)
	for i = 1, 16 do task.delay(i*0.08, function()
		ring(pos, Color3.fromRGB(255,42,42), 1, 6+i*5, 1.45)
	end) end
	burst(pos, Color3.fromRGB(255,60,60), 160, 55, 2.1, 0.62, 0.06)
	burst(pos+Vector3.new(0,2,0), Color3.fromRGB(200,30,30), 90, 42, 1.8, 0.50, 0.05)
	sphere(pos, Color3.fromRGB(255,42,42), 45, 1.7, 0.08)
	for i = 1, 12 do task.delay(i*0.09, function()
		local a = math.rad(i*30)
		slash(pos+Vector3.new(math.cos(a)*9,1,math.sin(a)*9),
			Vector3.new(math.cos(a),0,math.sin(a)), Color3.fromRGB(255,110,110), 5, 14, 65)
	end) end
	for i = 1, 8 do task.delay(i*0.15, function()
		local a = math.rad(i*45)
		pillar(pos+Vector3.new(math.cos(a)*14,0,math.sin(a)*14), Color3.fromRGB(255,42,42), 2.5, 28, 2.2)
	end) end
end

local function v3_F(char)
	local root = char:FindFirstChild("HumanoidRootPart"); if not root then return end
	local pos  = root.Position; local fwd = root.CFrame.LookVector
	playSound(SOUNDS.dark_pulse, 1.1, 0.78, pos)
	cutscene("Hadō #90: Release", "Kurohitsugi — Serbest Bırak", Color3.fromRGB(88,0,200), 2.5)
	shake(0.38, 2.8); lFlash(Color3.fromRGB(18,0,45), 0.38)
	for i = 1, 12 do task.delay(i*0.08, function()
		ring(pos, Color3.fromRGB(65,0,145), 20-i*1.6, 1, 0.55, 0.35)
	end) end
	task.wait(0.72)
	playSound(SOUNDS.beam, 1.1, 0.72, pos)
	beam(pos+fwd*2+Vector3.new(0,1.5,0), fwd, Color3.fromRGB(95,0,230), 6.5, 70, 1.2)
	task.delay(0.48, function()
		local hit = pos + fwd*67
		playSound(SOUNDS.explosion, 1.2, 0.76, hit)
		for i = 1, 22 do
			shard(hit+Vector3.new((math.random()-0.5)*22,(math.random()-0.5)*14+5,
				(math.random()-0.5)*22), Color3.fromRGB(22,0,55), 5.5, 1.5)
		end
		shake(0.45, 1.8)
		sphere(hit, Color3.fromRGB(85,0,200), 38, 1.5, 0.08)
		burst(hit, Color3.fromRGB(128,0,255), 140, 58, 1.8, 0.62, 0.06)
		for i = 1, 12 do task.delay(i*0.1, function()
			ring(hit, Color3.fromRGB(75,0,170), 1, 7+i*6, 1.4)
		end) end
		for i = 1, 6 do task.delay(i*0.2, function()
			local a = math.rad(i*60)
			pillar(hit+Vector3.new(math.cos(a)*10,0,math.sin(a)*10), Color3.fromRGB(95,0,220), 3.5, 32, 2.0)
		end) end
	end)
end

local function v3_Z(char)
	local root = char:FindFirstChild("HumanoidRootPart"); if not root then return end
	local pos  = root.Position; local fwd = root.CFrame.LookVector
	playSound(SOUNDS.power_up, 1.1, 0.9, pos); shake(0.18, 1.2)
	lFlash(Color3.fromRGB(50,0,0), 0.2)
	for i = 1, 6 do task.delay(i*0.10, function()
		burst(pos+fwd*(i*4), Color3.fromRGB(255,70,70), 55, 38, 1.1, 0.48, 0.05)
		ring(pos+fwd*(i*4), Color3.fromRGB(255,42,42), 0.5, 9+i*2.5, 0.82)
	end) end
	task.delay(0.68, function()
		sphere(pos+fwd*25, Color3.fromRGB(255,42,42), 30, 1.3, 0.12)
		for i = 1, 5 do task.delay(i*0.12, function()
			pillar(pos+fwd*25+Vector3.new((math.random()-0.5)*14,0,(math.random()-0.5)*14),
				Color3.fromRGB(255,55,55), 2.2, 20, 1.6)
		end) end
	end)
end

local function v3_X(char)
	local root = char:FindFirstChild("HumanoidRootPart"); if not root then return end
	local pos  = root.Position

	playSound(SOUNDS.bankai, 1.2, 0.82, pos)
	cutscene("★  BANKAI", "Kurohitsugi: Tensa Zangetsu", Color3.fromRGB(255,205,70), 4.0)
	shake(0.65, 5.0); lFlash(Color3.fromRGB(80,50,0), 0.52)

	-- Hazırlık: spiral halkaları
	for i = 1, 20 do task.delay(i*0.10, function()
		ring(pos, Color3.fromRGB(255,200,60), 30-i*1.4, 1, 0.75, 0.3)
	end) end

	spawnSpiralOrbs(pos+Vector3.new(0,4,0), Color3.fromRGB(255,205,70), 12, 9, 3.5, 2.2)
	spawnSpiralOrbs(pos+Vector3.new(0,2,0), Color3.fromRGB(255,80,80), 8, 6, 2.5, 2.2)

	task.wait(2.2)
	playSound(SOUNDS.explosion, 1.3, 0.75, pos)
	shake(0.8, 4.0); lFlash(Color3.fromRGB(100,60,0), 0.65)

	-- Mega patlama
	burst(pos, Color3.fromRGB(255,205,70), 400, 90, 4.2, 0.72, 0.06)
	burst(pos, Color3.fromRGB(255,100,100), 250, 72, 3.8, 0.60, 0.05)
	burst(pos, Color3.fromRGB(255,255,200), 180, 60, 3.2, 0.50, 0.04)
	sphere(pos, Color3.fromRGB(255,210,70),  80, 2.4, 0.06)
	sphere(pos, Color3.fromRGB(255,150,50),  55, 1.8, 0.05)

	for i = 1, 24 do task.delay(i*0.08, function()
		ring(pos, Color3.fromRGB(255,205,70), 1, 10+i*8, 2.2)
	end) end

	for i = 1, 18 do task.delay(0.12+i*0.10, function()
		local a = math.rad(i*20)
		pillar(pos+Vector3.new(math.cos(a)*18,0,math.sin(a)*18), Color3.fromRGB(255,200,60), 4, 50, 3.5)
	end) end

	for i = 1, 16 do task.delay(i*0.16, function()
		local a = math.rad(i*22.5+math.random()*30)
		lightning(pos+Vector3.new(math.cos(a)*16,55,math.sin(a)*16),
			pos+Vector3.new(math.cos(a)*16,0,math.sin(a)*16),
			Color3.fromRGB(255,220,80), 20, 0.50)
	end) end

	-- Kalıcı altın parıltı
	task.delay(0.8, function()
		for j = 1, 5 do task.delay(j*0.4, function()
			spawnSpiralOrbs(pos+Vector3.new(0,3,0), Color3.fromRGB(255,210,70), 6, 10, 2.5, 1.0)
		end) end
	end)
end

-- ─── VFX Tablosu ─────────────────────────────────────────────
local VFX_MAP = {
	AIZEN = {
		V1 = {v1_Q, v1_E, v1_R},
		V2 = {v2_Q, v2_E, v2_R, v2_F, v2_Z},
		V3 = {v3_Q, v3_E, v3_R, v3_F, v3_Z, v3_X},
	},
}

-- ─── Uzak VFX Olayı ──────────────────────────────────────────
vfxRemote.OnClientEvent:Connect(function(charId, version, skillIdx, char)
	local map = VFX_MAP[charId]
	if not map then return end
	local vMap = map[version]
	if not vMap then return end
	local fn = vMap[skillIdx]
	if fn then task.spawn(fn, char) end
end)

-- ─── Karakter Seçildi ────────────────────────────────────────
charSelectedEvt.OnClientEvent:Connect(function(charId, version)
	currentChar    = charId
	currentVersion = version

	if charId == "AIZEN" and version == "V3" then
		local char = player.Character
		if char then
			task.spawn(hogyokuTransform, char)
		end
	else
		startAura(version)
	end

	-- HUD'u bilgilendir
	local hud = player.PlayerGui:FindFirstChild("ShinigamiUI")
	if hud then
		local hudFrame = hud:FindFirstChild("HUD")
		if hudFrame then
			hudFrame.Visible = true
		end
	end
end)

-- ─── Versiyon Yükseltildi ────────────────────────────────────
versionUpgEvt.OnClientEvent:Connect(function(charId, newVer)
	currentVersion = newVer
	if charId == "AIZEN" and newVer == "V3" then
		local char = player.Character
		if char then task.spawn(hogyokuTransform, char) end
	else
		startAura(newVer)
	end
end)

-- ─── Giriş Sistemi (Klavye + Mobil) ──────────────────────────
local SKILL_KEYS = {
	V1 = {
		[Enum.KeyCode.Q] = 1,
		[Enum.KeyCode.E] = 2,
		[Enum.KeyCode.R] = 3,
	},
	V2 = {
		[Enum.KeyCode.Q] = 1,
		[Enum.KeyCode.E] = 2,
		[Enum.KeyCode.R] = 3,
		[Enum.KeyCode.F] = 4,
		[Enum.KeyCode.Z] = 5,
	},
	V3 = {
		[Enum.KeyCode.Q] = 1,
		[Enum.KeyCode.E] = 2,
		[Enum.KeyCode.R] = 3,
		[Enum.KeyCode.F] = 4,
		[Enum.KeyCode.Z] = 5,
		[Enum.KeyCode.X] = 6,
	},
}

local SKILL_COOLDOWNS = {
	V1 = {5, 8, 16},
	V2 = {5, 9, 15, 22, 13},
	V3 = {4, 9, 15, 23, 12, 32},
}

local function canUseSkill(ver, idx)
	local key  = ver .. "_" .. idx
	local last = localCooldowns[key]
	if not last then return true end
	local cds  = SKILL_COOLDOWNS[ver]
	if not cds or not cds[idx] then return true end
	return (os.clock() - last) >= cds[idx]
end

local function markSkillUsed(ver, idx)
	localCooldowns[ver .. "_" .. idx] = os.clock()
end

UserInputService.InputBegan:Connect(function(input, gpe)
	if gpe then return end
	if not currentChar then return end
	local char = player.Character
	if not char then return end
	local hum  = char:FindFirstChild("Humanoid")
	if not hum or hum.Health <= 0 then return end

	local keyMap = SKILL_KEYS[currentVersion]
	if not keyMap then return end
	local idx = keyMap[input.KeyCode]
	if not idx then return end

	if not canUseSkill(currentVersion, idx) then return end
	markSkillUsed(currentVersion, idx)

	-- Sunucuya bildir (hasar sunucuda hesaplanır)
	useSkillRemote:FireServer(currentChar, currentVersion, idx)

	-- Yerel VFX hemen çal (gecikme hissi olmasın)
	local map = VFX_MAP[currentChar]
	if map and map[currentVersion] and map[currentVersion][idx] then
		task.spawn(map[currentVersion][idx], char)
	end
end)

-- Karakter değiştiğinde aura sıfırla
player.CharacterAdded:Connect(function()
	task.wait(1.5)
	if currentChar and currentVersion then
		startAura(currentVersion)
	end
end)

print("[SNG BATTLEGROUND] AizenChar_LocalScript yüklendi | V1:3 V2:5 V3:6 ✓")
