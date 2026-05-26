/** URL аватара пользователя для img src */
export function resolveAvatarUrl(avatar) {
  if (!avatar) return null;
  if (avatar.startsWith('http://') || avatar.startsWith('https://')) {
    return avatar;
  }
  if (avatar.startsWith('/storage/') || avatar.startsWith('/img/')) {
    return avatar;
  }
  if (avatar.startsWith('storage/')) {
    return `/${avatar}`;
  }
  if (avatar.startsWith('avatars/')) {
    return `/${avatar}`;
  }
  if (avatar.startsWith('/')) {
    return avatar;
  }
  return `/${avatar}`;
}
