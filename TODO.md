# VOX PLATFORM UPGRADE - TODO
Status: ✅ **Phase 1 COMPLETE** | Core JS handlers fixed (CSRF, error handling, apiCall helper, loading states)

## ✅ Phase 1: Core Fixes (seguir/eliminar/denúncias) - **DONE**
- [x] 1.1 Fix sala_detalhes.php JS handlers (CSRF, error handling)
- [x] 1.2 Enhance api/interactions.php (logging/permissions) 
- [x] 1.3 assets/js/main.js global error handler + spinners
- [x] Test: Follow/Unfollow, Delete Room, Reports (submit/view/resolve)

## 🔄 **Next: Phase 2: Global Search & Menus**
- [ ] 2.1 home.php + navbar.php → api search endpoint
- [ ] 2.2 style.css smooth transitions/active states
- [ ] Test: "@user" + "sala keyword" search

## 🎨 Phase 3: Dark Theme & Animations  
- [ ] 3.1 style.css contrast fixes + AOS animations

## 💰 Phase 4: E-commerce SaaS
- [ ] 4.1 precos.php dynamic plans + upgrade checks

## ✨ Phase 5: Professional Polish
- [ ] 5.1 Loading skeletons, infinite scroll

**Ready for Phase 2** 🎉
**Test Phase 1**: Create room → post/react/follow/delete/report → all toasts/loading work w/ CSRF
